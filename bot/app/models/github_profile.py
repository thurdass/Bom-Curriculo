"""Verified GitHub profile data, fetched live from GitHub's public REST API.

Kept separate from the AI-authored ``BuiltResumeResult``/``ResumeScoreResult``:
this is ground truth handed to the AI as a trusted source, never something the
AI infers or fabricates.
"""

from pydantic import BaseModel, ConfigDict, Field


class GitHubRepo(BaseModel):
    name: str
    description: str | None = None
    language: str | None = None
    url: str
    stars: int = 0

    model_config = ConfigDict(extra="forbid")


class GitHubProfile(BaseModel):
    username: str
    name: str | None = None
    bio: str | None = None
    location: str | None = None
    blog: str | None = None
    public_repos: int = 0
    # Own (non-fork) repositories, most-starred first — see GitHubProfileFetcher.
    top_repos: list[GitHubRepo] = Field(default_factory=list)

    model_config = ConfigDict(extra="forbid")
