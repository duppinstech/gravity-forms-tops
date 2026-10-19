@AGENTS.md

# Claude Code Project Guide

GF TowX Integration is a WordPress plugin maintained by Duppins Technology. It maps Gravity Forms submissions to TOPS by TowXchange.

## Read before changing code

- [.claude/rules/](.claude/rules/): focused implementation rules.

## Project facts

- Bootstrap: `gravity-forms-tops.php`
- Main integration: `includes/class-gf-tops-addon.php`
- XML and API request: `includes/class-gf-tops-xml.php`, `includes/class-gf-tops-http.php`
- Secret handling: `includes/class-gf-tops-secrets.php` (`get_form_settings()` returns stored ciphertext; use `get_runtime_form_settings()` only to call TowX)
- Entry mapping: `includes/class-gf-tops-entry.php`
- Request log table: `includes/class-gf-tops-request-log.php`; privacy export / erase: `includes/class-gf-tops-privacy.php`
- Hooks reference: `docs/hooks.md` (update it when adding or changing a filter)
- Release ZIP: `.github/workflows/release.yml`, triggered by `v*` tags; package contents come from `bin/build-zip.sh`.
- Updater: `includes/class-gf-tops-github-updater.php` is the **reference implementation** of `docs/github-updater-standard.md`, copied into other Duppins Technology plugins. Behavior changes go through the standard: update the doc and tests, bump `STANDARD_VERSION`.
- Plugins screen: `includes/class-gf-tops-plugin-info.php` (plugin-info standard v1.0.0). Keep that class verbatim except the class name; behavior changes belong in the scaffold copy.
- CI: `.github/workflows/ci.yml` (lint, PHPCS, PHPUnit on PHP 7.4–8.4). Actions are pinned to commit SHAs in both workflows.
- Step-by-step playbooks: `.claude/playbooks/` (bug fix, feature change, release).
- Requirements: WordPress 6.2+, PHP 7.4+, and active Gravity Forms.

## Development expectations

- Prefer existing WordPress/Gravity Forms APIs and project conventions.
- Keep user-facing strings translatable with the `gravity-forms-tops` text domain and escape output at the point of use.
- Unit tests cover secrets, XML, HTTP host matching, entry mapping, the updater, privacy export / erase, and isolated add-on methods (via a `GFAddOn` stub and reflection) using stubs in `tests/bootstrap.php`; screens, hooks and the Create Call flow still need WordPress + Gravity Forms.
- For a release, bump the version in the plugin header, `GF_TOPS_VERSION` and `readme.txt` Stable tag (the release workflow fails on a mismatch), add a changelog entry (a non-empty `= x.y.z =` section under `== Changelog ==` in readme.txt, plus the same entry in CHANGELOG.md; CI and the release fail without the readme.txt section, and that section becomes the GitHub release notes), and inspect package contents; tagging `v*` causes GitHub Actions to create the plugin ZIP.
- Keep author and ownership metadata aligned with Duppins Technology. Client names describe intended use only, not ownership.

## Review board

| Agent | Department | Reviews |
|---|---|---|
| `security-reviewer` | Security | Auth, nonces, escaping, credentials, HTTP/TLS, update integrity, uninstall |
| `legal-reviewer` | Legal and compliance | License and copyright consistency, dependency licenses, trademarks, privacy and personal data, legal text |
| `finance-reviewer` | Finance | Pricing and licensing-model impact, operating cost (API calls, storage), support burden, revenue risk |
| `product-reviewer` | Product and UX | Fit with `INTENT.md` and recorded decisions, admin and visitor experience, accessibility, i18n, upgrade impact |
| `qa-reviewer` | QA and release | Runs `composer check`, test coverage, compatibility, release readiness, manual test plan |
| `docs-support-reviewer` | Docs and support | Docs match behavior, stale references, troubleshooting notes |

Reviewers are read-only. Fix "must fix" items, take owner decisions to the owner, and record decisions in `.claude/memory/decisions.md`.

## Local-only settings

Use `CLAUDE.local.md` or `.claude/settings.local.json` for personal machine-specific guidance. Both are ignored by Git. Never put credentials in them if the workspace may be shared or backed up.
