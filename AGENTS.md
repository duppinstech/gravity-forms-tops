# Repository Guidance for Coding Agents

This file applies to all work in this repository.

## Start here

- Read [intent.md](intent.md) for the plugin's purpose and boundaries.
- Read [plan.md](plan.md) for known SDLC follow-up work; do not treat it as authorization to expand a task.
- Read [CLAUDE.md](CLAUDE.md) for repository-specific commands and conventions.
- Follow [CONTRIBUTING.md](CONTRIBUTING.md) and [SECURITY.md](SECURITY.md).
- Read [OWNERSHIP.md](OWNERSHIP.md): authorship and copyright are Odell Duppins / Duppins Technology; the client is named only as who the work is for.

## Working rules

- Preserve the existing WordPress plugin APIs and compatibility with PHP 7.4 and WordPress 6.2+.
- Keep changes focused. Do not introduce dependencies, test frameworks, or runtime services without a task-specific reason.
- Never add credentials, private customer data, or production API responses to the repository.
- For TowX HTTP, XML, authentication, logging, and uninstall changes, review the security and data-handling impact.
- Validation tooling is dev-only Composer: `composer lint`, `composer phpcs`, `composer test`. Run them before claiming a check passed; state which ones ran. Integration with WordPress, Gravity Forms and TowX is still a manual check.
- Never pass credential-bearing XML or settings to logs, emails, transients or the request log; use `GF_Tops_Xml::redact_for_log()`.
- Before completing a change, inspect the diff and report validation performed and any environment-dependent checks not run.
