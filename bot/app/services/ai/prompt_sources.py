"""Shared source-formatting for the score and resume-construction prompts."""

from app.models.github_profile import GitHubProfile
from app.models.resume_analysis import SkillItem


def build_source_sections(
    resume_text: str,
    linkedin_text: str | None = None,
    github_url: str | None = None,
    github_profile: GitHubProfile | None = None,
    portfolio_url: str | None = None,
    additional_skills: list[SkillItem] | None = None,
) -> list[str]:
    sources = [f"Resume text:\n{resume_text}"]
    if linkedin_text:
        sources.append(f"LinkedIn resume text:\n{linkedin_text}")
    if github_profile:
        sources.append(_format_github_profile(github_profile, github_url))
    elif github_url:
        sources.append(
            f"User's GitHub link (add it to header.links under the key \"GitHub\"): {github_url}"
        )
    if portfolio_url:
        sources.append(
            f"User's portfolio link (add it to header.links under the key \"Portfolio\"): {portfolio_url}"
        )
    if additional_skills:
        skills_list = ", ".join(
            f"{skill.name} ({skill.years} anos)" if skill.years is not None else skill.name
            for skill in additional_skills
        )
        sources.append(
            f"Additional skills reported by the user, to merge into the skills list "
            f"(name and years of experience): {skills_list}"
        )
    return sources


def _format_github_profile(profile: GitHubProfile, github_url: str | None) -> str:
    lines = [
        f'Verified GitHub profile for "{profile.username}" (real data fetched live from '
        f"GitHub, use exactly as given — add the link to header.links under the key "
        f'"GitHub"): {github_url or f"https://github.com/{profile.username}"}'
    ]
    if profile.name:
        lines.append(f"Name: {profile.name}")
    if profile.bio:
        lines.append(f"Bio: {profile.bio}")
    if profile.location:
        lines.append(f"Location: {profile.location}")
    if profile.blog:
        lines.append(f"Website: {profile.blog}")
    lines.append(f"Public repositories: {profile.public_repos}")
    if profile.top_repos:
        lines.append(
            "Top own (non-fork) repositories — you may add relevant ones as projects, "
            "but never invent repositories beyond this list:"
        )
        for repo in profile.top_repos:
            language = f" [{repo.language}]" if repo.language else ""
            description = f" — {repo.description}" if repo.description else ""
            lines.append(f"- {repo.name}{language}{description} ({repo.url}, {repo.stars} stars)")
    return "\n".join(lines)
