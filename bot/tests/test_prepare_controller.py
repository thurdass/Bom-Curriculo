import asyncio
import json
from pathlib import Path

from dependency_injector import providers

from app.main import app
from app.models.resume_analysis import BuiltResumeResult
from tests.test_build_controller import (
    BASE_RESUME_TEXT,
    FakeDocumentReaderAggregator,
    FakeGitHubProfileFetcher,
    request_app,
)


def forbid_inference():
    raise AssertionError("Preparation must not instantiate the LLM manager")


def test_prepare_returns_sources_without_inference():
    with (
        app.container.resume_analysis_manager.override(providers.Callable(forbid_inference)),
        app.container.document_reader_aggregator.override(providers.Object(FakeDocumentReaderAggregator())),
        app.container.github_profile_fetcher.override(providers.Object(FakeGitHubProfileFetcher())),
    ):
        response = asyncio.run(request_app(
            "POST", "/api/v1/prepare",
            files={"resume_cv": ("cv.pdf", b"fixture"), "resume_linkedin": ("linkedin.pdf", b"fixture")},
            data={"github_url": "https://github.com/example", "portfolio_url": "https://example.test",
                  "additional_skills": json.dumps([{"name": "PHP", "years": 3}])},
        ))
    assert response.status_code == 200
    assert response.json() == {
        "version": 1, "resume_text": BASE_RESUME_TEXT, "linkedin_text": BASE_RESUME_TEXT,
        "github_profile": None, "github_url": "https://github.com/example",
        "portfolio_url": "https://example.test", "additional_skills": [{"name": "PHP", "years": 3}],
    }


def test_prepare_rejects_missing_or_invalid_documents():
    for files in ({}, {"resume_cv": ("cv.txt", b"fixture")}):
        response = asyncio.run(request_app("POST", "/api/v1/prepare", files=files))
        assert response.status_code == 422


def test_prepare_rejects_empty_extraction():
    with app.container.document_reader_aggregator.override(providers.Object(FakeDocumentReaderAggregator(""))):
        response = asyncio.run(request_app("POST", "/api/v1/prepare", files={"resume_cv": ("cv.pdf", b"fixture")}))
    assert response.status_code == 422


def test_laravel_fixture_matches_python_contract():
    fixture = Path(__file__).parents[2] / "backend/tests/Fixtures/generated_resume_payload.json"
    payload = json.loads(fixture.read_text())
    assert BuiltResumeResult.model_validate(payload).model_dump(mode="json") == payload
