# Bom Currículo - ATS Resume Builder

https://bomcurriculo.tech

## UNDER CONSTRUCTION

Build ATS-friendly resumes using AI.

Phase 1 now generates resumes directly from Laravel through Ollama (`qwen3:4b`).
Python remains responsible for document extraction and GitHub enrichment.
See the [migration report and operating guide](docs/migracao-llm-laravel.md) for
contracts, configuration, rollback, runtime evidence, and remaining work.

## About

Bom Currículo is an open-source project that generates professional resumes optimized for Applicant Tracking Systems (ATS).

The system can use multiple optional sources of information, including:

- Current resume
- LinkedIn Profile (PDF export)
- GitHub profile
- Portfolio
- Personal information
- Target job description

The user does not need to provide every source. The only requirement is enough information to generate a resume.

## Features

- LinkedIn profile analysis
- GitHub profile analysis
- Portfolio analysis
- Job description matching
- ATS-friendly resume generation
- Resume optimization

## Screen prototype

An initial interface prototype was also developed in Figma to validate the user experience, feature organization, and the platform's main flow before final implementation.

The screen is still evolving, but it already represents the first visual proposal of the user dashboard, including:

- ATS score overview;
- General resume performance indicator;
- AI-powered optimization tips;
- List of uploaded resumes;
- Application progress tracking;
- Sidebar menu with access to the platform’s main areas.


> **Note:** the design is still under development and may be adjusted as the product evolves.

![Project screens preview](./docs/assets/figma-preview.png)

## Roadmap

- [ ] Resume generation
- [ ] LinkedIn PDF parser
- [ ] GitHub integration
- [ ] Portfolio analysis
- [ ] Job matching
- [ ] Multiple templates
- [ ] Export to PDF
- [ ] Export to DOCX

## Contributing

Contributions are welcome!

Please read CONTRIBUTING.md before submitting a Pull Request.

## Running the project

Create the environment files:

```bash
cp .env.example .env
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env
cp bot/.env.example bot/.env
```

On Windows PowerShell:

```powershell
Copy-Item .env.example .env
Copy-Item backend/.env.example backend/.env
Copy-Item frontend/.env.example frontend/.env
Copy-Item bot/.env.example bot/.env
```

```bash
docker compose up --build
```

The Docker stack includes Ollama and automatically downloads the model defined
by `OLLAMA_MODEL` (default: `qwen3:4b`) into the persistent `ollama_data`
volume. The first startup can take several minutes because that model is about
2.5 GB. Laravel reaches Ollama through the Docker network at
`http://ollama:11434` and uses the bot only to prepare document sources.

The local default favors CPU-only development. Resume requests disable Qwen's
thinking mode, cap generated output at 2048 tokens, and use a 16384-token context.
Laravel allows 30 seconds for document preparation and 240 seconds for Ollama;
Nginx waits up to 310 seconds. Generation is synchronous and has no automatic
provider fallback. Set `RESUME_GENERATION_DRIVER=python` to explicitly restore
the legacy bot inference path, whose Laravel timeout remains 260 seconds.
CPU inference can still take tens of seconds or minutes; see the report for
the measured synthetic test and its limitations.

To confirm that the model is available or download it again:

```bash
docker compose exec ollama ollama list
docker compose exec ollama ollama pull qwen3:4b
```

Set `OLLAMA_MODEL` in the root `.env` before starting the stack to use another
model. `OLLAMA_BASE_URL` may point Laravel and the legacy bot at another Ollama
deployment; its address must be reachable from both containers. Model changes
require explicit configuration and validation on the target hardware.

Services:

- Frontend: http://localhost:5173
- Backend: http://localhost:9000
- Bot: http://localhost:8000
- Ollama: http://localhost:11434

Health endpoints:

- Backend: http://localhost:9000/up
- Bot: http://localhost:8000/health

## License

This project is licensed under the MIT License.
