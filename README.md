# Bom Currículo - ATS Resume Builder

https://bomcurriculo.tech

## UNDER CONSTRUCTION

Build ATS-friendly resumes using AI.

Laravel extracts PDF/DOCX files, enriches public GitHub profiles, validates data,
and generates resumes directly through Ollama (`qwen3:4b`). No Python service is required.
See the [migration report and operating guide](docs/migracao-llm-laravel.md) for
contracts, configuration, tests, and remaining work.
The [ATS PDF quality report](docs/qualidade-pdf-ats.md) documents layout,
pagination, language handling, and validation across different professional profiles.

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

- [x] Resume generation
- [x] LinkedIn PDF parser
- [x] GitHub integration
- [ ] Portfolio analysis
- [ ] Job matching
- [ ] Multiple templates
- [x] Export to PDF
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
```

On Windows PowerShell:

```powershell
Copy-Item .env.example .env
Copy-Item backend/.env.example backend/.env
Copy-Item frontend/.env.example frontend/.env
```

```bash
docker compose up --build
```

The Docker stack includes Ollama and automatically downloads the model defined
by `OLLAMA_MODEL` (default: `qwen3:4b`) into the persistent `ollama_data`
volume. The first startup can take several minutes because that model is about
2.5 GB. Laravel reaches Ollama through the Docker network at
`http://ollama:11434`. PDF extraction uses Poppler, installed in the backend image;
DOCX parsing and GitHub enrichment run directly in Laravel.

The local default favors CPU-only development. Resume requests disable Qwen's
thinking mode, cap generated output at 2048 tokens, and use a 16384-token context.
Laravel allows 10 seconds per PDF extraction, 10 seconds per GitHub request
(up to two), and 240 seconds for Ollama. Nginx waits up to 310 seconds.
Generation is synchronous and has no automatic provider fallback.
CPU inference can still take tens of seconds or minutes; see the report for
the measured synthetic test and its limitations.

To confirm that the model is available or download it again:

```bash
docker compose exec ollama ollama list
docker compose exec ollama ollama pull qwen3:4b
```

Set `OLLAMA_MODEL` in the root `.env` before starting the stack to use another
model. `OLLAMA_BASE_URL` may point Laravel at another Ollama
deployment; its address must be reachable from the backend. Model changes
require explicit configuration and validation on the target hardware.

Services:

- Frontend: http://localhost:5173
- Backend: http://localhost:9000
- Ollama: http://localhost:11434

Health endpoints:

- Backend: http://localhost:9000/up

## License

This project is licensed under the MIT License.
