"""Application configuration.

Non-secret values are read from ``config.yaml``. Secrets (API keys) are read
only from the environment / ``.env`` and are never written to the YAML file.
A handful of legacy environment variables (``IA_PROVIDER``, ...) still
override their ``config.yaml`` counterpart, preserving current deployment
behavior.
"""

from __future__ import annotations

import os
from dataclasses import dataclass, field
from pathlib import Path

import yaml
from dotenv import load_dotenv

PROJECT_ROOT = Path(__file__).resolve().parents[2]

load_dotenv(PROJECT_ROOT / ".env")

_PROVIDER_KEY_ENV_VARS = {
    "groq": "GROQ_API_KEY",
    "gemini": "GEMINI_API_KEY",
    "deepseek": "DEEPSEEK_API_KEY",
    "openai": "OPENAI_API_KEY",
}


@dataclass(frozen=True)
class ProviderSettings:
    model: str
    timeout_seconds: float
    api_key: str = ""
    base_url: str | None = None

    def is_configured(self) -> bool:
        if self.base_url is not None:
            return bool(self.base_url.strip() and self.model.strip())
        return bool(self.api_key.strip())


@dataclass(frozen=True)
class AISettings:
    enabled_by_default: bool
    output_language: str
    provider: str
    provider_chain: tuple[str, ...]
    providers: dict[str, ProviderSettings] = field(default_factory=dict)


@dataclass(frozen=True)
class ServerSettings:
    host: str
    port: int
    log_level: str


@dataclass(frozen=True)
class SecuritySettings:
    # Shared secret the Laravel backend must send as `Authorization: Bearer
    # <api_key>` on every `/api/v1/*` call. Secret, so it only ever comes from
    # the environment / .env — never from config.yaml.
    api_key: str = ""


@dataclass(frozen=True)
class GitHubSettings:
    # Optional: raises GitHub's unauthenticated REST API rate limit (60
    # requests/hour per IP) to 5000/hour when set. Never required — profile
    # enrichment just falls back to unauthenticated calls without it.
    token: str = ""


@dataclass(frozen=True)
class Settings:
    server: ServerSettings
    ai: AISettings
    security: SecuritySettings = field(default_factory=SecuritySettings)
    github: GitHubSettings = field(default_factory=GitHubSettings)

    @classmethod
    def load(cls, config_path: Path | None = None) -> "Settings":
        path = config_path or (PROJECT_ROOT / "config.yaml")
        raw: dict = yaml.safe_load(path.read_text()) if path.exists() else {}

        server_raw = raw.get("server") or {}
        ai_raw = raw.get("ai") or {}

        return cls(
            server=cls._build_server_settings(server_raw),
            ai=cls._build_ai_settings(ai_raw),
            security=cls._build_security_settings(),
            github=cls._build_github_settings(),
        )

    @staticmethod
    def _build_server_settings(raw: dict) -> ServerSettings:
        return ServerSettings(
            host=raw.get("host", "0.0.0.0"),
            port=int(raw.get("port", 8000)),
            log_level=os.getenv("LOG_LEVEL", raw.get("log_level", "INFO")),
        )

    @staticmethod
    def _build_security_settings() -> SecuritySettings:
        return SecuritySettings(api_key=os.getenv("BOT_API_KEY", ""))

    @staticmethod
    def _build_github_settings() -> GitHubSettings:
        return GitHubSettings(token=os.getenv("GITHUB_TOKEN", ""))

    @staticmethod
    def _build_ai_settings(raw: dict) -> AISettings:
        providers = {
            name: ProviderSettings(
                model=provider_raw["model"],
                timeout_seconds=float(provider_raw.get("timeout_seconds", 120.0)),
                api_key=os.getenv(_PROVIDER_KEY_ENV_VARS.get(name, ""), ""),
                base_url=provider_raw.get("base_url"),
            )
            for name, provider_raw in (raw.get("providers") or {}).items()
        }
        default_chain = ",".join(raw.get("provider_chain") or [])
        chain = tuple(
            item.strip().lower()
            for item in os.getenv("IA_PROVIDER_CHAIN", default_chain).split(",")
            if item.strip()
        )
        return AISettings(
            enabled_by_default=_env_flag("USAR_IA_PADRAO", raw.get("enabled_by_default", True)),
            output_language=raw.get("output_language", "pt-BR"),
            provider=os.getenv("IA_PROVIDER", raw.get("provider", "auto")).strip().lower(),
            provider_chain=chain,
            providers=providers,
        )


def _env_flag(name: str, default: bool) -> bool:
    raw = os.getenv(name)
    if raw is None:
        return default
    return raw.strip().lower() not in {"0", "false", "nao", "não", "off"}
