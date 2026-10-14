# Claude Code Project Guide

Gravity Forms TOPS / TowX Integration is a WordPress plugin maintained by Duppins Technology. It maps Gravity Forms submissions to TowX / TOPS API requests.

## Read before changing code

- [intent.md](intent.md): product intent and non-goals.
- [plan.md](plan.md): tracked SDLC and deployment follow-ups.
- [CONTRIBUTING.md](CONTRIBUTING.md): contribution workflow and code ownership map.
- [SECURITY.md](SECURITY.md): credential handling and vulnerability reporting.
- [.claude/rules/](.claude/rules/): focused implementation rules.

## Project facts

- Bootstrap: `gravity-forms-tops.php`
- Main integration: `includes/class-gf-tops-addon.php`
- XML and API request: `includes/class-gf-tops-xml.php`, `includes/class-gf-tops-http.php`
- Secret handling: `includes/class-gf-tops-secrets.php`
- Entry mapping: `includes/class-gf-tops-entry.php`
- Release ZIP: `.github/workflows/release.yml`, triggered by `v*` tags.
- Requirements: WordPress 6.2+, PHP 7.4+, and active Gravity Forms.

## Development expectations

- Preserve the existing WordPress and PHP compatibility floor. Prefer existing WordPress/Gravity Forms APIs and project conventions.
- Keep user-facing strings translatable with the `gravity-forms-tops` text domain and escape output at the point of use.
- Do not log credentials or add secrets, production customer data, or real API responses to fixtures or documentation.
- The repository currently has no Composer, PHPUnit, or npm test configuration. Do not report automated tests as run unless tooling is added and executed.
- For a release, inspect version references and package contents; tagging `v*` causes GitHub Actions to create the plugin ZIP.
- Keep author and ownership metadata aligned with Duppins Technology. Client names describe intended use only, not ownership.

## Local-only settings

Use `CLAUDE.local.md` or `.claude/settings.local.json` for personal machine-specific guidance. Both are ignored by Git. Never put credentials in them if the workspace may be shared or backed up.
