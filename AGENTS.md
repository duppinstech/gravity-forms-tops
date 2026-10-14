# Repository Guidance for Coding Agents

This file applies to all work in this repository.

## Start here

- Read [intent.md](intent.md) for the plugin's purpose and boundaries.
- Read [plan.md](plan.md) for known SDLC follow-up work; do not treat it as authorization to expand a task.
- Read [CLAUDE.md](CLAUDE.md) for repository-specific commands and conventions.
- Follow [CONTRIBUTING.md](CONTRIBUTING.md) and [SECURITY.md](SECURITY.md).

## Working rules

- Preserve the existing WordPress plugin APIs and compatibility with PHP 7.4 and WordPress 6.2+.
- Keep changes focused. Do not introduce dependencies, test frameworks, or runtime services without a task-specific reason.
- Never add credentials, private customer data, or production API responses to the repository.
- For TowX HTTP, XML, authentication, logging, and uninstall changes, review the security and data-handling impact.
- Check available tooling before claiming a test or lint command passed. There is currently no Composer, PHPUnit, or npm test setup.
- Before completing a change, inspect the diff and report validation performed and any environment-dependent checks not run.
