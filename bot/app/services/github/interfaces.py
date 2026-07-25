from abc import ABC, abstractmethod

from app.models.github_profile import GitHubProfile


class GitHubProfileFetcherInterface(ABC):
    """Fetch a verified public GitHub profile + top repositories from a github.com URL."""

    @abstractmethod
    async def fetch_profile(self, github_url: str) -> GitHubProfile | None:
        """Return the fetched profile, or None if it can't be verified.

        None covers every non-fatal failure: a URL that isn't a GitHub profile
        link, an unknown user, a rate limit, or a network error — callers
        should fall back gracefully (e.g. to the bare link) rather than fail
        the request over an optional enrichment.
        """
        ...
