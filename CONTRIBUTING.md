# Contributing

Thank you for contributing!

## How to contribute

- Fork this repository.
- Create a new branch.
- Make your changes.
- Commit them: `git add . && git commit -m "My changes" && git push origin development`
- Submit a Pull Request.

## Pull request template
*You must use this template on yout pull request:*

```md
## Contribution title

**Have you used AI to vibe code?**
Response.
**Have you used AI as a research source?**
Response.
**Have REVIEWED your code before request a review?**
Response.
**Have you tested locally?**
Response.

### Fixes:
Description of what you have fixed. You can also upload screenshots if needed.

```

## Before opening a PR

- **Backend (PHP):** run `vendor/bin/pint` before committing — it auto-fixes code style. If you don't have PHP installed, run it via Docker:
  `docker run --rm -v ${PWD}/backend:/app -w /app php:8.4-cli vendor/bin/pint`
- **Mobile (Flutter):** run `dart format .` inside `mobile/` before committing. Without Flutter installed:
  `docker run --rm -v ${PWD}/mobile:/app -w /app dart:stable dart format .`
- **Keep your branch up to date** with `development` before opening/merging your PR — CI runs against your branch's current state, so a stale branch can show failures that were already fixed elsewhere.
- **Keep PRs focused**: don't mix formatting-only changes with bug fixes or new features in the same PR — it makes review and rollback much easier.

## Coding Style

- Keep code readable.
- Write comments when necessary.
- Follow existing project patterns.

## Reporting Bugs

Please open an Issue describing:

- Expected behavior
- Actual behavior
- Steps to reproduce

## Suggesting Features

Open an Issue with:

- Feature description
- Motivation
- Possible implementation