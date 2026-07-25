"""Shared-secret auth for the Laravel -> bot service boundary.

Every request under ``/api/v1/`` must carry ``Authorization: Bearer
<BOT_API_KEY>`` matching the bot's own configured secret (see
``SecuritySettings``). Everything else (health check, metrics, the landing
page, the OpenAPI docs) stays open, since those aren't the AI-costing
endpoints this guards.
"""

from __future__ import annotations

import hmac
from collections.abc import Callable

from quart import Quart
from quart import request as quart_request

from app.core.settings import Settings
from app.models.error_response import ErrorResponse

PROTECTED_PREFIX = "/api/v1/"


def register_api_key_auth(app: Quart, load_settings: Callable[[], Settings]) -> None:
    """Reject unauthenticated calls to every ``/api/v1/*`` route.

    ``load_settings`` is called on every request (not cached), matching how
    the rest of ``Settings`` is already reloaded per-request via the DI
    container — so a rotated ``BOT_API_KEY`` takes effect without a restart.
    """

    @app.before_request
    async def _require_api_key():
        if not quart_request.path.startswith(PROTECTED_PREFIX):
            return None

        expected = load_settings().security.api_key
        if not expected:
            # Fail closed: an unconfigured secret must not fall back to open access.
            return ErrorResponse(detail="bot API key is not configured").model_dump(mode="json"), 500

        provided = _bearer_token(quart_request.headers.get("Authorization", ""))
        if provided is None or not hmac.compare_digest(provided, expected):
            return ErrorResponse(detail="missing or invalid API key").model_dump(mode="json"), 401

        return None


def _bearer_token(header_value: str) -> str | None:
    prefix = "Bearer "
    if not header_value.startswith(prefix):
        return None
    token = header_value[len(prefix):].strip()
    return token or None
