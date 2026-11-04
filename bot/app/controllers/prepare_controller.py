"""Temporary extraction boundary for Laravel-owned resume generation. No LLM calls."""

from dependency_injector.wiring import Provide, inject
from quart import Blueprint
from quart_schema import DataSource, document_request, document_response, tag

from app.controllers.resume_input import RequestRejected, resolve_resume_input
from app.core.container import Container
from app.models.error_response import ErrorResponse
from app.models.resume_analysis import PreparedResume, ResumeAnalysisRequest
from app.services.github.interfaces import GitHubProfileFetcherInterface
from app.services.parsing.interfaces import ResumeContentValidatorInterface
from app.services.parsing.readers.interfaces import DocumentReaderAggregatorInterface

prepare_blueprint = Blueprint("prepare", __name__)


@prepare_blueprint.post("/api/v1/prepare")
@tag(["Resume"])
@document_request(ResumeAnalysisRequest, source=DataSource.FORM_MULTIPART)
@document_response(PreparedResume, 200)
@document_response(ErrorResponse, 422)
@inject
async def prepare(
    document_reader_aggregator: DocumentReaderAggregatorInterface = Provide[Container.document_reader_aggregator],
    resume_content_validator: ResumeContentValidatorInterface = Provide[Container.resume_content_validator],
    github_profile_fetcher: GitHubProfileFetcherInterface = Provide[Container.github_profile_fetcher],
):
    try:
        parsed, text, linkedin, github = await resolve_resume_input(
            document_reader_aggregator, resume_content_validator, github_profile_fetcher
        )
    except RequestRejected as rejection:
        return rejection.body, rejection.status

    return PreparedResume(
        resume_text=text,
        linkedin_text=linkedin,
        github_profile=github.model_dump(mode="json") if github else None,
        github_url=parsed.github_url,
        portfolio_url=parsed.portfolio_url,
        additional_skills=parsed.additional_skills,
    ).model_dump(mode="json")
