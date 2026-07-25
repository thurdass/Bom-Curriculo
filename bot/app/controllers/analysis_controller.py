from dependency_injector.wiring import Provide, inject
from quart import Blueprint
from quart_schema import DataSource, document_request, document_response, tag

from app.controllers.resume_input import RequestRejected, resolve_resume_input
from app.core.container import Container
from app.models.error_response import ErrorResponse
from app.models.resume_analysis import ResumeAnalysisRequest, ResumeScoreResult
from app.providers.base import AIProviderError
from app.services.ai.interfaces import ResumeAnalysisManagerInterface
from app.services.github.interfaces import GitHubProfileFetcherInterface
from app.services.parsing.interfaces import ResumeContentValidatorInterface
from app.services.parsing.readers.interfaces import DocumentReaderAggregatorInterface

analysis_blueprint = Blueprint("analysis", __name__)


@analysis_blueprint.post("/api/v1/analyze")
@tag(["Resume"])
@document_request(ResumeAnalysisRequest, source=DataSource.FORM_MULTIPART)
@document_response(ResumeScoreResult, 200)
@document_response(ErrorResponse, 422)
@document_response(ErrorResponse, 503)
@inject
async def analyze(
    resume_analysis_manager: ResumeAnalysisManagerInterface = Provide[Container.resume_analysis_manager],
    document_reader_aggregator: DocumentReaderAggregatorInterface = Provide[Container.document_reader_aggregator],
    resume_content_validator: ResumeContentValidatorInterface = Provide[Container.resume_content_validator],
    github_profile_fetcher: GitHubProfileFetcherInterface = Provide[Container.github_profile_fetcher],
):
    """Judge the resume exactly as given: an ATS score plus one improvement suggestion."""

    try:
        parsed_request, resume_text, linkedin_text, github_profile = await resolve_resume_input(
            document_reader_aggregator, resume_content_validator, github_profile_fetcher
        )
    except RequestRejected as rejection:
        return rejection.body, rejection.status

    try:
        result = await resume_analysis_manager.score_resume(
            resume_text,
            linkedin_text=linkedin_text,
            github_url=parsed_request.github_url,
            github_profile=github_profile,
            portfolio_url=parsed_request.portfolio_url,
            additional_skills=parsed_request.additional_skills,
        )
    except AIProviderError as error:
        return ErrorResponse(detail=str(error)).model_dump(mode="json"), 503

    return result.model_dump(mode="json")
