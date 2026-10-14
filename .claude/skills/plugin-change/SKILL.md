---
name: plugin-change
description: Use when implementing or changing behavior in the Gravity Forms TOPS WordPress plugin.
---

# Plugin Change Workflow

1. Read `intent.md`, `AGENTS.md`, and the nearest relevant class, asset, or test before editing.
2. Trace the input, permission checks, mutation or network call, and output for the behavior being changed.
3. Preserve PHP 7.4 and WordPress 6.2 compatibility and the existing public settings and filters unless the request requires a migration.
4. Make the smallest focused change. Add or update tests only using the repository's established tooling; do not introduce a framework incidentally.
5. Run `composer lint`, `composer phpcs` and `composer test` (add unit tests for standalone classes under `tests/`), inspect the diff, and report any integration check that requires WordPress, Gravity Forms, or QA credentials.
