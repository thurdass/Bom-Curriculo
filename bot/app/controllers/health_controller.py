import logging

from prometheus_client import CONTENT_TYPE_LATEST, generate_latest
from quart import Blueprint
from quart_schema import document_response, hide, tag

from app.models.health_status import HealthStatus

logger = logging.getLogger(__name__)

health_blueprint = Blueprint("health", __name__)


@health_blueprint.get("/health")
@tag(["Monitoring"])
@document_response(HealthStatus, 200)
async def health_check() -> dict[str, str]:
    logger.info("health check ok")
    return HealthStatus().model_dump()


@health_blueprint.get("/metrics")
@hide
async def metrics():
    """Prometheus scrape endpoint (plain text, not JSON) — excluded from the OpenAPI spec."""
    return generate_latest(), 200, {"Content-Type": CONTENT_TYPE_LATEST}
