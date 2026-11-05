# Bom Currículo — Laravel backend

Laravel 13 manages authentication, uploads, resume processing, review and PDF
downloads. PDF extraction uses Poppler; DOCX parsing uses PHP DOM and ZIP.
Public GitHub enrichment and Ollama generation run directly in Laravel.
Resume processing is synchronous and requires no Python service or queue worker.

See the [architecture and operating guide](../docs/migracao-llm-laravel.md) for
configuration, API contracts, compatibility, timeouts, logs and isolated tests.

## Docker development

From the repository root, create the environment files using the examples and run:

```bash
docker compose up -d --build
```

The backend is available at `http://localhost:9000`; its health endpoint is `/up`.
The frontend is available at `http://localhost:5173`. The entrypoint applies
migrations and development seeds; PostgreSQL, uploaded files and Ollama models
use persistent volumes. Preserve existing `.env` files and volumes when updating.

## Running outside Docker

Install PHP 8.4 with the extensions required by Composer, Composer 2, Node.js,
PostgreSQL and Poppler (`poppler-utils` on Debian/Ubuntu). Ollama must be reachable
and the configured model, initially `qwen3:4b`, must be installed.

From this directory:

```bash
composer install
cp .env.example .env
php artisan key:generate
npm ci
npm run build
```

Configure database access, `OLLAMA_BASE_URL` and, optionally, `GITHUB_TOKEN` in
`.env`. Set `DB_HOST=127.0.0.1` and the published database port when connecting
from the host to Docker PostgreSQL. Adjust `PDFTOTEXT_BINARY` if necessary.
Then initialize the development database and start the API:

```bash
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=9000
```

## Tests

Use the disposable-container commands in the operating guide for SQLite tests
without accessing application data. The default suite uses simulated external
HTTP responses; the real Ollama integration is explicitly opt-in.

```bash
vendor/bin/pint --test
```

Routes and their authentication requirements are defined in `routes/api.php`.
The generation endpoint is `POST /api/client/services/resumes/process`, receiving
`user_resume_id` from the upload response. The former `/services/bot/process`
path remains an alias to preserve frontend compatibility.
