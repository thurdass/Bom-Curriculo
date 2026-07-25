from quart import Quart
from quart_schema import QuartSchema
from quart_schema.openapi import HttpSecurityScheme, Info, Tag

from app.controllers.analysis_controller import analysis_blueprint
from app.controllers.build_controller import build_blueprint
from app.controllers.health_controller import health_blueprint
from app.controllers.root_controller import root_blueprint
from app.core.auth import register_api_key_auth
from app.core.container import Container
from app.core.logging import configure_logging


def create_app() -> Quart:
    container = Container()
    configure_logging(container.settings().server.log_level)

    container.wire(modules=[
        "app.controllers.analysis_controller",
        "app.controllers.build_controller",
    ])

    quart_app = Quart(__name__)
    quart_app.container = container

    # Swagger UI at /docs, ReDoc at /redocs, raw spec at /openapi.json.
    QuartSchema(
        quart_app,
        info=Info(
            title="Bom Currículo Bot API",
            version="0.1.0",
            description=(
                "ATS resume scoring/reconstruction service consumed by the Laravel "
                "backend. Send a base CV (inline text or a URL) plus optional "
                "LinkedIn/GitHub/portfolio sources to `/api/v1/analyze` for a "
                "quality score, or to `/api/v1/build` for a fully reconstructed, "
                "ATS-optimized resume. See the bot README for the full integration "
                "guide."
            ),
        ),
        tags=[
            Tag(name="Resume", description="Resume scoring and reconstruction."),
            Tag(name="Monitoring", description="Health and metrics endpoints."),
        ],
        security_schemes={
            # Shared secret configured as BOT_API_KEY on the bot; send it as
            # `Authorization: Bearer <key>`.
            "ApiKeyAuth": HttpSecurityScheme(scheme="bearer"),
        },
    )
    register_api_key_auth(quart_app, container.settings)

    quart_app.register_blueprint(root_blueprint)
    quart_app.register_blueprint(health_blueprint)
    quart_app.register_blueprint(analysis_blueprint)
    quart_app.register_blueprint(build_blueprint)
    return quart_app


app = create_app()
