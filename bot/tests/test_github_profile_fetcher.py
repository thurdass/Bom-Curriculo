"""Tests for GitHubProfileFetcher (app/services/github/github_profile_fetcher.py)."""

import asyncio

import httpx

from app.core.settings import AISettings, ServerSettings, Settings
from app.services.github.github_profile_fetcher import GitHubProfileFetcher

SETTINGS = Settings(
    server=ServerSettings(host="0.0.0.0", port=8000, log_level="INFO"),
    ai=AISettings(enabled_by_default=True, output_language="pt-BR", provider="auto", provider_chain=()),
)

USER_PAYLOAD = {
    "login": "octocat",
    "name": "The Octocat",
    "bio": "GitHub mascot",
    "location": "San Francisco",
    "blog": "https://github.blog",
    "public_repos": 2,
}

REPOS_PAYLOAD = [
    {
        "name": "Hello-World",
        "description": "My first repo",
        "language": "Python",
        "html_url": "https://github.com/octocat/Hello-World",
        "stargazers_count": 80,
        "fork": False,
    },
    {
        "name": "a-fork",
        "description": "Forked repo",
        "language": "Python",
        "html_url": "https://github.com/octocat/a-fork",
        "stargazers_count": 999,
        "fork": True,
    },
    {
        "name": "Spoon-Knife",
        "description": None,
        "language": "Ruby",
        "html_url": "https://github.com/octocat/Spoon-Knife",
        "stargazers_count": 5,
        "fork": False,
    },
]


def _handler_for(user_status: int = 200, user_payload=None, repos_payload=None):
    def handler(request: httpx.Request) -> httpx.Response:
        if request.url.path.endswith("/repos"):
            return httpx.Response(200, json=repos_payload if repos_payload is not None else REPOS_PAYLOAD)
        return httpx.Response(user_status, json=user_payload if user_payload is not None else USER_PAYLOAD)

    return handler


def test_fetch_profile_returns_none_for_non_github_url():
    fetcher = GitHubProfileFetcher(settings=SETTINGS)

    result = asyncio.run(fetcher.fetch_profile("https://example.com/octocat"))

    assert result is None


def test_fetch_profile_returns_none_for_unknown_user():
    fetcher = GitHubProfileFetcher(
        settings=SETTINGS, transport=httpx.MockTransport(_handler_for(user_status=404))
    )

    result = asyncio.run(fetcher.fetch_profile("https://github.com/does-not-exist"))

    assert result is None


def test_fetch_profile_returns_verified_profile_and_filters_forks():
    fetcher = GitHubProfileFetcher(settings=SETTINGS, transport=httpx.MockTransport(_handler_for()))

    profile = asyncio.run(fetcher.fetch_profile("https://github.com/octocat"))

    assert profile is not None
    assert profile.username == "octocat"
    assert profile.name == "The Octocat"
    assert profile.bio == "GitHub mascot"
    assert profile.public_repos == 2
    # The fork is excluded even though it has more stars than every real entry.
    assert [repo.name for repo in profile.top_repos] == ["Hello-World", "Spoon-Knife"]
    assert profile.top_repos[0].stars == 80
    assert profile.top_repos[0].language == "Python"


def test_fetch_profile_returns_none_on_network_error():
    def handler(request: httpx.Request) -> httpx.Response:
        raise httpx.ConnectError("boom", request=request)

    fetcher = GitHubProfileFetcher(settings=SETTINGS, transport=httpx.MockTransport(handler))

    result = asyncio.run(fetcher.fetch_profile("https://github.com/octocat"))

    assert result is None


def test_fetch_profile_sends_bearer_token_when_configured():
    captured_headers = {}

    def handler(request: httpx.Request) -> httpx.Response:
        captured_headers.update(request.headers)
        return _handler_for()(request)

    settings_with_token = Settings(
        server=SETTINGS.server,
        ai=SETTINGS.ai,
        github=type(SETTINGS.github)(token="test-github-token"),
    )
    fetcher = GitHubProfileFetcher(
        settings=settings_with_token, transport=httpx.MockTransport(handler)
    )

    asyncio.run(fetcher.fetch_profile("https://github.com/octocat"))

    assert captured_headers.get("authorization") == "Bearer test-github-token"


def test_fetch_profile_ignores_reserved_path_segments():
    fetcher = GitHubProfileFetcher(settings=SETTINGS)

    result = asyncio.run(fetcher.fetch_profile("https://github.com/orgs/some-org"))

    assert result is None
