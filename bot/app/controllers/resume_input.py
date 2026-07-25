"""Shared HTTP-boundary parsing/validation for the resume-score and resume-build endpoints."""

import json

from pydantic import ValidationError
from quart import request as quart_request

from app.models.error_response import ErrorResponse
from app.models.github_profile import GitHubProfile
from app.models.resume_analysis import ResumeAnalysisRequest
from app.services.github.interfaces import GitHubProfileFetcherInterface
from app.services.parsing.interfaces import ResumeContentValidatorInterface
from app.services.parsing.readers.interfaces import (
    DocumentReaderAggregatorInterface,
    UnsupportedDocumentFormat,
)

# resume_cv accepts PDF or DOCX; resume_linkedin only PDF (LinkedIn's own "save to
# PDF" export is the only format users realistically have for it).
_ALLOWED_EXTENSIONS = {
    "resume_cv": (".pdf", ".docx"),
    "resume_linkedin": (".pdf",),
}


class RequestRejected(Exception):
    """A 4xx the caller should return as-is: ``(body, status)``."""

    def __init__(self, body: dict, status: int) -> None:
        super().__init__(body)
        self.body = body
        self.status = status


async def resolve_resume_input(
    document_reader_aggregator: DocumentReaderAggregatorInterface,
    resume_content_validator: ResumeContentValidatorInterface,
    github_profile_fetcher: GitHubProfileFetcherInterface,
) -> tuple[ResumeAnalysisRequest, str, str | None, GitHubProfile | None]:
    """Parse the multipart request and resolve resume/LinkedIn text, raising
    ``RequestRejected`` on any 422."""

    form = await quart_request.form
    files = await quart_request.files

    resume_cv_file = files.get("resume_cv")
    resume_linkedin_file = files.get("resume_linkedin")
    _validate_extension(resume_cv_file, "resume_cv")
    _validate_extension(resume_linkedin_file, "resume_linkedin")

    try:
        additional_skills = json.loads(form.get("additional_skills") or "[]")
    except json.JSONDecodeError as error:
        raise RequestRejected(
            ErrorResponse(detail="additional_skills must be valid JSON").model_dump(mode="json"), 422
        ) from error

    payload = {
        "github_url": form.get("github_url"),
        "portfolio_url": form.get("portfolio_url"),
        "additional_skills": additional_skills,
    }
    if resume_cv_file is not None:
        payload["resume_cv"] = resume_cv_file.read()
    if resume_linkedin_file is not None:
        payload["resume_linkedin"] = resume_linkedin_file.read()

    try:
        parsed_request = ResumeAnalysisRequest.model_validate(payload)
    except ValidationError as error:
        # include_context=False: pydantic embeds the raw exception object in each
        # error's `ctx`, which isn't JSON-serializable and would turn this 422
        # into an unhandled 500.
        raise RequestRejected(
            ErrorResponse(detail=error.errors(include_context=False)).model_dump(mode="json"), 422
        ) from error

    resume_text = _read_upload(document_reader_aggregator, parsed_request.resume_cv, resume_cv_file, "resume_cv")
    linkedin_text = None
    if parsed_request.resume_linkedin is not None:
        linkedin_text = _read_upload(
            document_reader_aggregator, parsed_request.resume_linkedin, resume_linkedin_file, "resume_linkedin"
        )

    # resume_cv is required, so this only fires if its extracted text is empty
    # (e.g. a scanned-image PDF with no extractable text).
    if not resume_text.strip():
        raise RequestRejected(ErrorResponse(detail="empty").model_dump(mode="json"), 422)

    validation = resume_content_validator.validate(resume_text)
    if not validation.is_valid:
        raise RequestRejected(ErrorResponse(detail=validation.reason).model_dump(mode="json"), 422)

    # Best-effort: a github_url that isn't a real/reachable profile just means no
    # enrichment (see GitHubProfileFetcherInterface) — never rejects the request.
    github_profile = None
    if parsed_request.github_url:
        github_profile = await github_profile_fetcher.fetch_profile(parsed_request.github_url)

    return parsed_request, resume_text, linkedin_text, github_profile


def _validate_extension(file_storage, field_name: str) -> None:
    if file_storage is None:
        return
    allowed = _ALLOWED_EXTENSIONS[field_name]
    filename = (file_storage.filename or "").lower()
    if not filename.endswith(allowed):
        raise RequestRejected(
            ErrorResponse(detail=_wrong_format_message(field_name, allowed)).model_dump(mode="json"), 422
        )


def _wrong_format_message(field_name: str, allowed: tuple[str, ...]) -> str:
    formats = " or ".join(extension.removeprefix(".").upper() for extension in allowed)
    return f"{field_name} must be a {formats} file"


def _read_upload(
    document_reader_aggregator: DocumentReaderAggregatorInterface,
    content: bytes,
    file_storage,
    field_name: str,
) -> str:
    filename = file_storage.filename or "upload"
    try:
        return document_reader_aggregator.read(content, filename)
    except UnsupportedDocumentFormat as error:
        message = _wrong_format_message(field_name, _ALLOWED_EXTENSIONS[field_name])
        raise RequestRejected(ErrorResponse(detail=message).model_dump(mode="json"), 422) from error
    except Exception as error:
        raise RequestRejected(
            ErrorResponse(detail=f"could not read {field_name}").model_dump(mode="json"), 422
        ) from error
