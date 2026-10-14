# Claude Code Project Guide

Gravity Forms TOPS / TowX Integration is a WordPress plugin maintained by Duppins Technology. It maps Gravity Forms submissions to TowX / TOPS API requests.

## Read before changing code

- [intent.md](intent.md): product intent and non-goals.
- [plan.md](plan.md): tracked SDLC and deployment follow-ups.
- [CONTRIBUTING.md](CONTRIBUTING.md): contribution workflow and code ownership map.
- [SECURITY.md](SECURITY.md): credential handling and vulnerability reporting.
- [OWNERSHIP.md](OWNERSHIP.md): who owns the product (Duppins Technology) and who it is licensed to.
- [.claude/rules/](.claude/rules/): focused implementation rules.

## Project facts

- Bootstrap: `gravity-forms-tops.php`
- Main integration: `includes/class-gf-tops-addon.php`
- XML and API request: `includes/class-gf-tops-xml.php`, `includes/class-gf-tops-http.php`
- Secret handling: `includes/class-gf-tops-secrets.php` (`get_form_settings()` returns stored ciphertext; use `get_runtime_form_settings()` only to call TowX)
- Entry mapping: `includes/class-gf-tops-entry.php`
- Release ZIP: `.github/workflows/release.yml`, triggered by `v*` tags; package contents come from `bin/build-zip.sh`.
- CI: `.github/workflows/ci.yml` (lint, PHPCS, PHPUnit on PHP 7.4–8.3).
- Step-by-step playbooks: `.claude/playbooks/` (bug fix, feature change, release).
- Requirements: WordPress 6.2+, PHP 7.4+, and active Gravity Forms.

## Development expectations

- Preserve the existing WordPress and PHP compatibility floor. Prefer existing WordPress/Gravity Forms APIs and project conventions.
- Keep user-facing strings translatable with the `gravity-forms-tops` text domain and escape output at the point of use.
- Do not log credentials or add secrets, production customer data, or real API responses to fixtures or documentation.
- Validation: `composer install`, then `composer lint`, `composer phpcs`, `composer test` (or `composer check`). Unit tests cover secrets, XML, HTTP host matching and entry mapping using stubs in `tests/bootstrap.php`; the add-on class needs WordPress + Gravity Forms and is verified manually. Report exactly which checks ran.
- For a release, bump the version in the plugin header, `GF_TOPS_VERSION` and `readme.txt` Stable tag (the release workflow fails on a mismatch), add a changelog entry, and inspect package contents; tagging `v*` causes GitHub Actions to create the plugin ZIP.
- Keep author and ownership metadata aligned with Duppins Technology. Client names describe intended use only, not ownership.

## Local-only settings

Use `CLAUDE.local.md` or `.claude/settings.local.json` for personal machine-specific guidance. Both are ignored by Git. Never put credentials in them if the workspace may be shared or backed up.
