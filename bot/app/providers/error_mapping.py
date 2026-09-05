"""Translate LangChain/native SDK exceptions into the service's stable error categories.

Concrete transport/parser exception types are handled first. SDK exception
names remain as a compatibility fallback because each LangChain integration
wraps a different provider SDK.
"""

import json
from collections.abc import Iterator

import httpx
from langchain_core.exceptions import OutputParserException
from pydantic import ValidationError

from app.providers.base import AIProviderError

_CONNECTION_ERROR_NAMES = {
    "APIConnectionError",
    "ConnectError",
    "ConnectionError",
    "ConnectionRefusedError",
    "ConnectionResetError",
    "NetworkError",
    "ProxyError",
}


def _exception_chain(error: Exception) -> Iterator[BaseException]:
    """Yield an exception and its explicit/implicit causes without looping."""

    current: BaseException | None = error
    seen: set[int] = set()

    while current is not None and id(current) not in seen:
        seen.add(id(current))
        yield current
        current = current.__cause__ or current.__context__


def _status_code(errors: tuple[BaseException, ...]) -> int | None:
    for error in errors:
        status = getattr(error, "status_code", None) or getattr(
            getattr(error, "response", None), "status_code", None
        )
        if isinstance(status, int):
            return status
    return None


def map_provider_error(provider_name: str, error: Exception) -> AIProviderError:
    if isinstance(error, AIProviderError):
        return error

    label = provider_name.capitalize()
    errors = tuple(_exception_chain(error))
    status_code = _status_code(errors)
    error_types = {type(item).__name__ for item in errors}

    if any(isinstance(item, json.JSONDecodeError) for item in errors):
        return AIProviderError(f"{label} returned invalid JSON.", category="invalid_json")
    if any(isinstance(item, ValidationError) for item in errors):
        return AIProviderError(
            f"{label} returned data outside the expected schema.",
            category="schema_validation_error",
        )
    if any(isinstance(item, OutputParserException) for item in errors):
        return AIProviderError(
            f"{label} returned a response that could not be parsed.",
            category="response_parsing_error",
        )
    if any(isinstance(item, (httpx.TimeoutException, TimeoutError)) for item in errors) or any(
        "Timeout" in error_type for error_type in error_types
    ):
        return AIProviderError(f"{label} timed out.", category="timeout")
    if any("RateLimit" in error_type for error_type in error_types) or status_code == 429:
        return AIProviderError(
            f"{label} rate-limited the request.", category="rate_limit_429", status_http=429
        )
    if any("Authentication" in error_type for error_type in error_types) or status_code == 401:
        return AIProviderError(
            f"{label} rejected the authentication.", category="auth_error_401", status_http=401
        )
    if any("PermissionDenied" in error_type for error_type in error_types) or status_code == 403:
        return AIProviderError(
            f"{label} rejected the requested permission.",
            category="permission_error_403",
            status_http=403,
        )
    if any("NotFound" in error_type for error_type in error_types) or status_code == 404:
        return AIProviderError(
            f"{label} did not recognize or did not make available the configured model.",
            category="invalid_model",
            status_http=404,
        )
    if status_code == 413:
        return AIProviderError(
            f"{label} rejected the request for exceeding the allowed size.",
            category="request_too_large",
            status_http=413,
        )
    if (
        any(
            "BadRequest" in error_type or "InvalidRequest" in error_type
            for error_type in error_types
        )
        or status_code == 400
    ):
        return AIProviderError(
            f"{label} rejected the request format.", category="invalid_request", status_http=400
        )
    if any(
        isinstance(item, (httpx.RequestError, httpx.InvalidURL, ConnectionError))
        for item in errors
    ) or bool(error_types & _CONNECTION_ERROR_NAMES):
        return AIProviderError(f"Could not connect to {label}.", category="network_error")
    if status_code is not None and status_code >= 500:
        return AIProviderError(
            f"{label} is temporarily unavailable.",
            category="provider_unavailable",
            status_http=status_code,
        )
    return AIProviderError(f"{label} returned an unclassified error.", category="unknown_provider_error")
