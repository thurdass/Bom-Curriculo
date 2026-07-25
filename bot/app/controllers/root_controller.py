"""Landing page: not part of the JSON API, just orientation for whoever hits `/` first."""

from quart import Blueprint, render_template_string
from quart_schema import hide

root_blueprint = Blueprint("root", __name__)

_PAGE = """
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Bom Currículo Bot API</title>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 40rem; margin: 3rem auto; padding: 0 1rem; color: #1a1a1a; }
    code { background: #f0f0f0; padding: 0.15rem 0.4rem; border-radius: 0.25rem; }
    pre { background: #f0f0f0; padding: 1rem; border-radius: 0.5rem; overflow-x: auto; }
    a { color: #2563eb; }
    ul { line-height: 1.9; }
  </style>
</head>
<body>
  <h1>Bom Currículo Bot API</h1>
  <p>
    Serviço Python que recebe um currículo (texto ou arquivo PDF/DOCX) e devolve
    uma nota ATS ou uma versão reconstruída e otimizada — consumido pelo backend
    Laravel do Bom Currículo.
  </p>

  <h2>Documentação</h2>
  <ul>
    <li><a href="/docs">/docs</a> — Swagger UI (testa as requisições no navegador)</li>
    <li><a href="/redocs">/redocs</a> — ReDoc (leitura da documentação)</li>
    <li><a href="/openapi.json">/openapi.json</a> — spec OpenAPI 3 crua</li>
  </ul>

  <h2>Endpoints principais</h2>
  <ul>
    <li><code>POST /api/v1/analyze</code> — só a nota ATS do currículo como está</li>
    <li><code>POST /api/v1/build</code> — reconstrói o currículo otimizado para ATS</li>
    <li><a href="/health"><code>GET /health</code></a> — health check</li>
  </ul>

  <h2>Exemplo rápido</h2>
  <pre>curl -X POST http://localhost:8000/api/v1/analyze \\
  -H "Content-Type: application/json" \\
  -d '{"resume_text": "Desenvolvedor Python com 2 anos de experiência..."}'</pre>

  <p>Guia completo de integração no <code>README.md</code> do repositório.</p>
</body>
</html>
"""


@root_blueprint.get("/")
@hide
async def index() -> str:
    return await render_template_string(_PAGE)
