"""Tests for POST /api/v1/build — reconstructing the best possible ATS resume."""

import asyncio
import json

from dependency_injector import providers
from httpx import ASGITransport, AsyncClient

from app.main import app
from app.models.resume_analysis import BuiltResumeResult, ProjectItem, ResumeHeader

BASE_RESUME_TEXT = (
    "Desenvolvedor backend com experiência em Python, FastAPI e SQL. Atuou em "
    "projetos de automação de currículos, integração com filas de mensagens e "
    "construção de APIs REST para times de produto. Formação em Ciência da "
    "Computação e experiência prévia como estagiário de TI."
)


class FakeDocumentReaderAggregator:
    """Ignores the uploaded bytes/filename and returns a fixed text."""

    def __init__(self, text: str = BASE_RESUME_TEXT) -> None:
        self._text = text

    def read(self, content: bytes, filename: str) -> str:
        return self._text


async def request_app(method: str, path: str, **parameters):
    """Call the ASGI application without an external server."""
    transport = ASGITransport(app=app)
    async with AsyncClient(transport=transport, base_url="http://test") as client:
        return await client.request(method, path, **parameters)


class FakeResumeAnalysisManager:
    async def build_resume(self, resume_text: str, **_kwargs) -> BuiltResumeResult:
        return BuiltResumeResult(
            score=90,
            professional_summary="Desenvolvedor backend com foco em Python e APIs REST.",
            header=ResumeHeader(name="João Silva"),
            projects=[
                ProjectItem(
                    title="Sistema de Tarefas",
                    start="2024-01",
                    end="2024-06",
                    technologies="Python, Flask",
                )
            ],
        )


def test_build_endpoint_returns_built_resume() -> None:
    app.container.resume_analysis_manager.override(providers.Object(FakeResumeAnalysisManager()))
    app.container.document_reader_aggregator.override(
        providers.Object(FakeDocumentReaderAggregator())
    )
    try:
        response = asyncio.run(
            request_app(
                "POST",
                "/api/v1/build",
                files={"resume_cv": ("cv.pdf", b"irrelevant, reader is faked", "application/pdf")},
            )
        )
    finally:
        app.container.resume_analysis_manager.reset_override()
        app.container.document_reader_aggregator.reset_override()

    assert response.status_code == 200
    result = response.json()
    assert result["score"] == 90
    assert "suggestion" not in result
    assert result["header"]["name"] == "João Silva"
    assert result["projects"] == [
        {
            "title": "Sistema de Tarefas",
            "start": "2024-01",
            "end": "2024-06",
            "technologies": "Python, Flask",
            "description": None,
            "url": None,
        }
    ]


def test_build_endpoint_rejects_missing_resume_cv() -> None:
    response = asyncio.run(
        request_app("POST", "/api/v1/build", data={"github_url": "https://github.com/foo"})
    )

    assert response.status_code == 422


class SpyResumeAnalysisManager:
    """Captures every argument build_resume was called with, for assertion."""

    def __init__(self) -> None:
        self.calls: list[dict] = []

    async def build_resume(self, resume_text: str, **kwargs) -> BuiltResumeResult:
        self.calls.append({"resume_text": resume_text, **kwargs})
        return BuiltResumeResult(score=90)


class FakeGitHubProfileFetcher:
    """Never hits the network: returns None (no enrichment) like an unresolvable profile."""

    async def fetch_profile(self, github_url: str) -> None:
        return None


def test_build_endpoint_forwards_every_supporting_source() -> None:
    spy = SpyResumeAnalysisManager()
    app.container.resume_analysis_manager.override(providers.Object(spy))
    app.container.github_profile_fetcher.override(providers.Object(FakeGitHubProfileFetcher()))
    app.container.document_reader_aggregator.override(
        providers.Object(FakeDocumentReaderAggregator())
    )
    try:
        response = asyncio.run(
            request_app(
                "POST",
                "/api/v1/build",
                data={
                    "github_url": "https://github.com/pedroaruana",
                    "portfolio_url": "https://pedroaruana.dev",
                    "additional_skills": json.dumps([{"name": "React", "years": 2}]),
                },
                files={"resume_cv": ("cv.pdf", b"irrelevant, reader is faked", "application/pdf")},
            )
        )
    finally:
        app.container.resume_analysis_manager.reset_override()
        app.container.github_profile_fetcher.reset_override()
        app.container.document_reader_aggregator.reset_override()

    assert response.status_code == 200
    call = spy.calls[0]
    assert call["resume_text"] == BASE_RESUME_TEXT
    assert call["github_url"] == "https://github.com/pedroaruana"
    assert call["github_profile"] is None
    assert call["portfolio_url"] == "https://pedroaruana.dev"
    assert [(skill.name, skill.years) for skill in call["additional_skills"]] == [("React", 2)]
