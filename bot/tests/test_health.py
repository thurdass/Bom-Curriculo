"""Tests for the application's public endpoints."""

import asyncio
import json
from pathlib import Path

from dependency_injector import providers
from httpx import ASGITransport, AsyncClient

from app.main import app
from app.models.resume_analysis import ResumeScoreResult
from app.services.parsing.interfaces import ResumeContentValidation

FIXTURES = Path(__file__).parent / "fixtures"

BASE_RESUME_TEXT = (
    "Desenvolvedor backend com experiência em Python, FastAPI e SQL. Atuou em "
    "projetos de automação de currículos, integração com filas de mensagens e "
    "construção de APIs REST para times de produto. Formação em Ciência da "
    "Computação e experiência prévia como estagiário de TI."
)


class FakeResumeContentValidator:
    def validate(self, text: str) -> ResumeContentValidation:
        return ResumeContentValidation(is_valid=True)


class FakeDocumentReaderAggregator:
    """Ignores the uploaded bytes/filename and returns a fixed text.

    Used by tests that care about request wiring (which field went where),
    not about actual PDF/DOCX parsing — that's covered separately by the
    real-file extraction test below.
    """

    def __init__(self, text: str = BASE_RESUME_TEXT) -> None:
        self._text = text

    def read(self, content: bytes, filename: str) -> str:
        return self._text


async def request_app(method: str, path: str, **parameters):
    """Call the ASGI application without an external server."""
    transport = ASGITransport(app=app)
    async with AsyncClient(transport=transport, base_url="http://test") as client:
        return await client.request(method, path, **parameters)


def test_health_behavior_01() -> None:
    response = asyncio.run(request_app("GET", "/health"))

    assert response.status_code == 200
    assert response.json() == {"status": "online"}


class FakeResumeAnalysisManager:
    async def score_resume(self, resume_text: str, **_kwargs) -> ResumeScoreResult:
        return ResumeScoreResult(score=80, suggestion="Adicione métricas de impacto.")


def test_analyze_endpoint_returns_score_and_suggestion() -> None:
    app.container.resume_analysis_manager.override(providers.Object(FakeResumeAnalysisManager()))
    app.container.document_reader_aggregator.override(
        providers.Object(FakeDocumentReaderAggregator())
    )
    try:
        response = asyncio.run(
            request_app(
                "POST",
                "/api/v1/analyze",
                files={"resume_cv": ("cv.pdf", b"irrelevant, reader is faked", "application/pdf")},
            )
        )
    finally:
        app.container.resume_analysis_manager.reset_override()
        app.container.document_reader_aggregator.reset_override()

    assert response.status_code == 200
    assert response.json() == {"score": 80, "suggestion": "Adicione métricas de impacto."}


def test_analyze_endpoint_rejects_empty_extracted_text() -> None:
    app.container.document_reader_aggregator.override(
        providers.Object(FakeDocumentReaderAggregator(text="   "))
    )
    try:
        response = asyncio.run(
            request_app(
                "POST",
                "/api/v1/analyze",
                files={"resume_cv": ("cv.pdf", b"a blank scan, say", "application/pdf")},
            )
        )
    finally:
        app.container.document_reader_aggregator.reset_override()

    assert response.status_code == 422
    assert response.json() == {"detail": "empty"}


def test_analyze_endpoint_rejects_missing_resume_cv() -> None:
    """No resume_cv given at all: 422, not a 500."""
    response = asyncio.run(
        request_app("POST", "/api/v1/analyze", data={"github_url": "https://github.com/foo"})
    )

    assert response.status_code == 422


class SpyResumeAnalysisManager:
    """Captures every argument score_resume was called with, for assertion."""

    def __init__(self) -> None:
        self.calls: list[dict] = []

    async def score_resume(self, resume_text: str, **kwargs) -> ResumeScoreResult:
        self.calls.append({"resume_text": resume_text, **kwargs})
        return ResumeScoreResult(score=80, suggestion="Adicione métricas de impacto.")


class FakeGitHubProfileFetcher:
    """Never hits the network: returns None (no enrichment) like an unresolvable profile."""

    async def fetch_profile(self, github_url: str) -> None:
        return None


def test_analyze_endpoint_forwards_every_supporting_source() -> None:
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
                "/api/v1/analyze",
                data={
                    "github_url": "https://github.com/pedroaruana",
                    "portfolio_url": "https://pedroaruana.dev",
                    "additional_skills": json.dumps(
                        [{"name": "React", "years": 2}, {"name": "Docker"}]
                    ),
                },
                files={"resume_cv": ("cv.pdf", b"irrelevant, reader is faked", "application/pdf")},
            )
        )
    finally:
        app.container.resume_analysis_manager.reset_override()
        app.container.github_profile_fetcher.reset_override()
        app.container.document_reader_aggregator.reset_override()

    assert response.status_code == 200
    assert len(spy.calls) == 1
    call = spy.calls[0]
    assert call["resume_text"] == BASE_RESUME_TEXT
    assert call["github_url"] == "https://github.com/pedroaruana"
    assert call["github_profile"] is None
    assert call["portfolio_url"] == "https://pedroaruana.dev"
    assert [(skill.name, skill.years) for skill in call["additional_skills"]] == [
        ("React", 2),
        ("Docker", None),
    ]


def test_analyze_endpoint_extracts_text_from_uploaded_cv_and_linkedin_files() -> None:
    """resume_cv/resume_linkedin arrive as real PDF/DOCX uploads, parsed by the
    actual DocumentReaderAggregator — not a fake, this exercises the real parser."""
    spy = SpyResumeAnalysisManager()
    app.container.resume_analysis_manager.override(providers.Object(spy))
    # The fixture is a short snippet, not a full resume — bypass content
    # validation since this test is about extraction, not that check.
    app.container.resume_content_validator.override(
        providers.Object(FakeResumeContentValidator())
    )
    try:
        response = asyncio.run(
            request_app(
                "POST",
                "/api/v1/analyze",
                files={
                    "resume_cv": (
                        "cv.docx",
                        (FIXTURES / "sample_resume.docx").read_bytes(),
                        "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
                    ),
                    "resume_linkedin": (
                        "linkedin.pdf",
                        (FIXTURES / "sample_resume.pdf").read_bytes(),
                        "application/pdf",
                    ),
                },
            )
        )
    finally:
        app.container.resume_analysis_manager.reset_override()
        app.container.resume_content_validator.reset_override()

    assert response.status_code == 200
    call = spy.calls[0]
    assert "PROFISSIONAL" in call["resume_text"]
    assert "PROFISSIONAL" in call["linkedin_text"]


def test_analyze_endpoint_rejects_unsupported_cv_file_extension() -> None:
    response = asyncio.run(
        request_app(
            "POST",
            "/api/v1/analyze",
            files={"resume_cv": ("cv.txt", b"plain text resume", "text/plain")},
        )
    )

    assert response.status_code == 422
    assert response.json() == {"detail": "resume_cv must be a PDF or DOCX file"}


def test_analyze_endpoint_rejects_docx_linkedin_file() -> None:
    """resume_linkedin only accepts PDF — a DOCX (valid for resume_cv) must be rejected."""
    response = asyncio.run(
        request_app(
            "POST",
            "/api/v1/analyze",
            files={
                "resume_linkedin": (
                    "linkedin.docx",
                    (FIXTURES / "sample_resume.docx").read_bytes(),
                    "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
                )
            },
        )
    )

    assert response.status_code == 422
    assert response.json() == {"detail": "resume_linkedin must be a PDF file"}
