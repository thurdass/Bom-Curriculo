"""Shared pytest fixtures."""

import pytest

TEST_API_KEY = "test-secret-key-for-pytest"
AUTH_HEADERS = {"Authorization": f"Bearer {TEST_API_KEY}"}


@pytest.fixture(autouse=True)
def _bot_api_key(monkeypatch: pytest.MonkeyPatch) -> None:
    """Every test gets a configured BOT_API_KEY, matching a real deployment.

    Tests exercising the unauthenticated/misconfigured path (see
    test_auth.py) override this per-test with monkeypatch.delenv.
    """
    monkeypatch.setenv("BOT_API_KEY", TEST_API_KEY)
