# SDLC and Deployment Plan

This file tracks follow-up work for the plugin. Check current code and workflow state before starting an item; the list is not an instruction to broaden unrelated changes.

## SDLC follow-ups

- [x] Choose a supported PHP lint / WordPress coding-standard setup and document reproducible local and CI commands. *(1.4.0: dev-only Composer; `composer lint`, `composer phpcs`, `composer test`; ruleset in `phpcs.xml.dist`; documented in README and CONTRIBUTING.)*
- [x] Add automated tests for credential encryption and migration, XML generation, and request/response handling without storing real credentials or customer data. *(1.4.0: `tests/SecretsTest.php`, `tests/XmlTest.php`, `tests/EntryTest.php`; fake values only.)*
- [x] Add a CI validation workflow after the supported checks and test environment are agreed. *(1.4.0: `.github/workflows/ci.yml`, PHP 7.4–8.3.)*
- [x] Review the release ZIP exclusion list when adding development-only files or new top-level directories. *(1.4.0: packaging moved to `bin/build-zip.sh`, built from tracked files only; release workflow checks tag vs. version and re-runs lint/tests.)*

## Deployment checks for 1.4.0 (need a WordPress environment)

These need WordPress, a licensed Gravity Forms install and authorized QA TowX credentials, so they cannot run in CI.

- [ ] **Install / upgrade:** on a staging copy of a production site (WordPress 6.2+, PHP 7.4+, Gravity Forms 2.5+), upgrade from 1.3.9 to 1.4.0. Confirm no PHP notices in `debug.log` and that Forms → TOPS log still lists earlier rows.
- [ ] **Secret migration:** on a form that still had plaintext credentials, confirm (DB: `wp_gf_form_meta`) the password and key now start with `gf_tops_e1:` after the upgrade, without re-saving the form.
- [ ] **Settings screen:** view page source of Form → Settings → TOPS; the password / authentication key must not appear in plaintext. Leave both blank, save, and confirm they are kept (Test authentication still succeeds).
- [ ] **Test authentication** against QA (Forms → Settings → TOPS → API environment = QA).
- [ ] **Dropdowns:** Make and Color fill; choosing a make loads models; submit with a validation error and confirm the chosen model is still selected.
- [ ] **Create Call (QA):** submit a test request; confirm the Call ID in the confirmation and in the request log, and that the logged XML shows `***` for Password / SessionID / AuthenticationKey.
- [ ] **VehicleInfo:** in the request log row, VehicleInfo shows names (for example `2020 Toyota Camry Blue`), not numeric keys. Repeat with **Resend Create Call**; the resend must also show names, and resending a successful row must ask for confirmation.
- [ ] **Error email:** set the API environment to an invalid custom URL, submit, and confirm the email contains no password, session ID or authentication key. Restore the environment.
- [ ] **Non-ASCII text:** submit with an accented name and a curly apostrophe (for example `José O’Brien`) and confirm how it appears in TowX. The plugin sends UTF-8 bytes but labels the request `charset=WINDOWS-1252`; if TowX shows garbled characters, open a fix to transcode to Windows-1252 (or change the declared charset) after confirming which one TowX expects.
- [ ] **Page cache:** if the site uses a full-page cache, exclude tow-form pages or confirm their cache lifetime is under 12 hours (see README → Production notes).
- [ ] **Release:** after the checks pass, tag `v1.4.0` on `main`, confirm the Release ZIP workflow succeeded, inspect `gravity-forms-tops.zip`, then update production sites via Plugins → Updates.
- [x] **Credential rotation:** not needed (confirmed by the owner, 2026-10-14).

## Deployment checks for 1.4.1 (updater standard)

- [ ] On a site running 1.4.0, update to 1.4.1 from Plugins → Updates. 1.4.0's old updater delivers it; every later update uses the new one.
- [ ] After updating: the plugin row shows **Enable auto-updates**, and **View details** shows the release notes with the Requirements block.
- [ ] Dashboard → Updates → **Check again** finds no update (current) without errors; `wp option get gf_tops_github_release --format=json` (or the `wp_options` / `wp_sitemeta` row) shows a stored ETag.
- [ ] Port the standard to Landing Page Manager, Landing Page Manager Pro and Radius SEO with the porting prompt (Pro blocked until its release delivery is decided; its repository is private).

## Product decisions (2026-10-14)

- **Request-log retention:** rows are kept indefinitely (until the plugin is uninstalled). No scheduled cleanup.
- **Public error detail:** visitors keep seeing TowX / transport error text in the error confirmation so they can quote it when reporting a problem. Leaning decision; revisit if the text exposes anything sensitive. Sites can already replace it per form with the custom error message setting.
- **Front-end ⚡ marker:** kept on the public form's Make, Model and Color labels.

## Open items

- **Formatting standard:** WordPress whitespace / Yoda sniffs are off in `phpcs.xml.dist`. Enabling them means a one-time `phpcbf` reformat in its own PR.

## Completion notes

Record completed deployment checks with the release or pull request. Do not record secrets, live payloads, or personally identifiable customer data here.
