"""Tests for the shared-secret auth guarding /api/v1/* (see app/core/auth.py)."""

import asyncio

from httpx import ASGITransport, AsyncClient

from app.main import app
from tests.conftest import TEST_API_KEY

RESUME_TEXT = (
    "Desenvolvedor backend com experiência em Python, FastAPI e SQL. Atuou em "
    "projetos de automação de currículos, integração com filas de mensagens e "
    "construção de APIs REST para times de produto."
)


async def request_app(method: str, path: str, **parameters):
    """Call the ASGI application without an external server (no default auth header)."""
    transport = ASGITransport(app=app)
    async with AsyncClient(transport=transport, base_url="http://test") as client:
        return await client.request(method, path, **parameters)


def test_analyze_rejects_request_without_authorization_header() -> None:
    response = asyncio.run(request_app("POST", "/api/v1/analyze", json={"resume_text": RESUME_TEXT}))

    assert response.status_code == 401


def test_analyze_rejects_wrong_api_key() -> None:
    response = asyncio.run(
        request_app(
            "POST",
            "/api/v1/analyze",
            json={"resume_text": RESUME_TEXT},
            headers={"Authorization": "Bearer wrong-key"},
        )
    )

    assert response.status_code == 401


def test_build_rejects_request_without_authorization_header() -> None:
    response = asyncio.run(request_app("POST", "/api/v1/build", json={"resume_text": RESUME_TEXT}))

    assert response.status_code == 401


def test_analyze_with_correct_bearer_token_passes_auth() -> None:
    """A well-formed key gets past auth into normal validation: 422 (missing
    resume source), not 401 — proves auth isn't what's rejecting the request."""
    response = asyncio.run(
        request_app(
            "POST",
            "/api/v1/analyze",
            json={},
            headers={"Authorization": f"Bearer {TEST_API_KEY}"},
        )
    )

    assert response.status_code == 422


def test_missing_bot_api_key_fails_closed(monkeypatch) -> None:
    monkeypatch.delenv("BOT_API_KEY", raising=False)

    response = asyncio.run(
        request_app(
            "POST",
            "/api/v1/analyze",
            json={"resume_text": RESUME_TEXT},
            headers={"Authorization": f"Bearer {TEST_API_KEY}"},
        )
    )

    assert response.status_code == 500


def test_health_endpoint_does_not_require_auth() -> None:
    response = asyncio.run(request_app("GET", "/health"))

    assert response.status_code == 200


def test_root_page_does_not_require_auth() -> None:
    response = asyncio.run(request_app("GET", "/"))

    assert response.status_code == 200


def test_openapi_spec_does_not_require_auth() -> None:
    response = asyncio.run(request_app("GET", "/openapi.json"))

    assert response.status_code == 200
