"""Fetches a user's public GitHub profile + top repositories from GitHub's REST API.

Best-effort enrichment: any failure (URL that isn't a GitHub profile link,
unknown user, rate limit, network error) returns None instead of raising —
the resume flow must not break just because GitHub is unreachable or the link
doesn't resolve to a real account. See GitHubProfileFetcherInterface.
"""

from __future__ import annotations

import logging
import re

import httpx

from app.core.settings import Settings
from app.models.github_profile import GitHubProfile, GitHubRepo
from app.services.github.interfaces import GitHubProfileFetcherInterface

logger = logging.getLogger(__name__)

_USERNAME_RE = re.compile(r"github\.com/([A-Za-z0-9](?:[A-Za-z0-9-]{0,38}))", re.IGNORECASE)
_API_BASE = "https://api.github.com"
_API_VERSION = "2022-11-28"
_TOP_REPOS_LIMIT = 6
_REPOS_PER_PAGE = 30
_TIMEOUT_SECONDS = 10.0

# First path segment on github.com that is a site section, not a username —
# fetching these as "users" would either 404 or return an unrelated account.
_RESERVED_PATHS = {
    "orgs",
    "organizations",
    "settings",
    "features",
    "marketplace",
    "sponsors",
    "notifications",
    "issues",
    "pulls",
    "topics",
    "collections",
    "trending",
    "explore",
    "about",
    "pricing",
    "contact",
    "security",
    "login",
    "join",
    "apps",
}


class GitHubProfileFetcher(GitHubProfileFetcherInterface):
    def __init__(
        self,
        settings: Settings | None = None,
        transport: httpx.BaseTransport | None = None,
    ) -> None:
        self._settings = settings or Settings.load()
        self._transport = transport

    async def fetch_profile(self, github_url: str) -> GitHubProfile | None:
        username = self._extract_username(github_url)
        if username is None:
            return None

        headers = {
            "Accept": "application/vnd.github+json",
            "X-GitHub-Api-Version": _API_VERSION,
        }
        token = self._settings.github.token
        if token:
            headers["Authorization"] = f"Bearer {token}"

        try:
            async with httpx.AsyncClient(
                timeout=_TIMEOUT_SECONDS, transport=self._transport, headers=headers
            ) as client:
                user_response = await client.get(f"{_API_BASE}/users/{username}")
                if user_response.status_code == 404:
                    return None
                user_response.raise_for_status()
                user_data = user_response.json()

                repos_response = await client.get(
                    f"{_API_BASE}/users/{username}/repos",
                    params={"sort": "pushed", "per_page": _REPOS_PER_PAGE},
                )
                repos_response.raise_for_status()
                repos_data = repos_response.json()
        except httpx.HTTPError as error:
            logger.warning("could not fetch GitHub profile for %s: %s", username, error)
            return None

        return GitHubProfile(
            username=user_data.get("login", username),
            name=user_data.get("name"),
            bio=user_data.get("bio"),
            location=user_data.get("location"),
            blog=user_data.get("blog") or None,
            public_repos=user_data.get("public_repos", 0),
            top_repos=self._top_repos(repos_data),
        )

    @staticmethod
    def _extract_username(github_url: str) -> str | None:
        match = _USERNAME_RE.search(github_url)
        if not match:
            return None
        username = match.group(1)
        if username.lower() in _RESERVED_PATHS:
            return None
        return username

    @staticmethod
    def _top_repos(repos_data: list[dict]) -> list[GitHubRepo]:
        non_forks = [repo for repo in repos_data if not repo.get("fork")]
        non_forks.sort(key=lambda repo: repo.get("stargazers_count", 0), reverse=True)
        return [
            GitHubRepo(
                name=repo["name"],
                description=repo.get("description"),
                language=repo.get("language"),
                url=repo["html_url"],
                stars=repo.get("stargazers_count", 0),
            )
            for repo in non_forks[:_TOP_REPOS_LIMIT]
        ]
