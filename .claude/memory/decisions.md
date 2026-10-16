# Recorded Project Decisions

- Preserve PHP 7.4 and WordPress 6.2 compatibility unless a support-policy change is explicitly approved.
- Keep this plugin focused on the Gravity Forms to TowX integration; do not add unrelated product features.
- Keep release packaging in GitHub Actions and use version tags matching `v*`.
- Treat credentials and customer submission data as sensitive; consult `SECURITY.md` for exact current storage behavior.
- Record durable decisions here with date, context, and consequence. Do not use this file as a substitute for an issue tracker.
- 2026-10-14: Adopted dev-only Composer tooling (PHPUnit 9.6, PHPCS with WordPress-Extra minus formatting sniffs, PHPCompatibilityWP 7.4+). Formatting sniffs (Yoda, alignment) stay off to avoid a whole-codebase reformat; enable them only as a dedicated formatting change.
- 2026-10-14: `get_form_settings()` returns secrets as stored (encrypted); `get_runtime_form_settings()` decrypts for TowX calls only. Consequence: never render runtime settings.
- 2026-10-14: TowX vehicle lists are cached in transients (1 hour default) keyed by a salted hash of API base + credentials.
- 2026-10-14: Request-log rows are retained indefinitely (until uninstall); do not add scheduled cleanup without a new decision.
- 2026-10-14: Visitors see TowX / transport error text in the error confirmation so they can report it (leaning decision; per-form custom error text can replace it).
- 2026-10-14: The ⚡ marker on public Make / Model / Color labels stays.
- 2026-10-14: Adopted the shared GitHub updater standard v1.0.0 (docs/github-updater-standard.md). This repo holds the reference implementation; other plugins copy the class verbatim with a renamed class. Private repositories are out of scope (LPM Pro delivery is an open decision).
