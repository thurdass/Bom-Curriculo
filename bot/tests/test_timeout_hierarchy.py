import re
from pathlib import Path

import yaml


REPOSITORY_ROOT = Path(__file__).resolve().parents[2]


def _example_environment() -> dict[str, str]:
    values = {}
    for line in (REPOSITORY_ROOT / ".env.example").read_text().splitlines():
        if line and not line.startswith("#") and "=" in line:
            key, value = line.split("=", 1)
            values[key] = value
    return values


def test_request_timeout_hierarchy_leaves_each_layer_time_to_respond() -> None:
    environment = _example_environment()
    bot_config = yaml.safe_load(
        (REPOSITORY_ROOT / "bot/config.yaml.example").read_text()
    )
    nginx_config = (REPOSITORY_ROOT / "frontend/nginx.conf").read_text()
    proxy_match = re.search(r"proxy_read_timeout\s+(\d+)s;", nginx_config)

    assert proxy_match is not None
    inference_deadline = float(bot_config["ai"]["inference_deadline_seconds"])
    laravel_timeout = float(environment["BOT_TIMEOUT_SECONDS"])
    proxy_timeout = float(proxy_match.group(1))

    assert inference_deadline == float(environment["BOT_INFERENCE_DEADLINE_SECONDS"])
    assert inference_deadline < laravel_timeout < proxy_timeout
    assert laravel_timeout - inference_deadline >= 20
    assert proxy_timeout - laravel_timeout >= 20
