# SDLC and Deployment Plan

This file tracks follow-up work for the plugin. Check current code and workflow state before starting an item; the list is not an instruction to broaden unrelated changes.

## SDLC follow-ups

- [x] Choose a supported PHP lint / WordPress coding-standard setup and document reproducible local and CI commands. *(1.4.0: dev-only Composer; `composer lint`, `composer phpcs`, `composer test`; ruleset in `phpcs.xml.dist`; documented in README and CONTRIBUTING.)*
- [x] Add automated tests for credential encryption and migration, XML generation, and request/response handling without storing real credentials or customer data. *(1.4.0: `tests/SecretsTest.php`, `tests/XmlTest.php`, `tests/EntryTest.php`; fake values only.)*
- [x] Add a CI validation workflow after the supported checks and test environment are agreed. *(1.4.0: `.github/workflows/ci.yml`, PHP 7.4–8.3.)*
- [x] Review the release ZIP exclusion list when adding development-only files or new top-level directories. *(1.4.0: packaging moved to `bin/build-zip.sh`, built from tracked files only; release workflow checks tag vs. version and re-runs lint/tests.)*

## Deployment checks for 1.4.0 (need a WordPress environment)

> 1.4.0 was tagged and released on 2026-10-14 before these checks were run. Run them together with the 1.4.1 and 1.5.0 checks on staging before tagging the next release.

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
- [x] **Release:** `v1.4.0` tagged on `main` and the release ZIP published (2026-10-14).
- [x] **Credential rotation:** not needed (confirmed by the owner, 2026-10-14).

## Deployment checks for 1.4.1 (updater standard)

- [ ] On a site running 1.4.0, update to 1.4.1 from Plugins → Updates. 1.4.0's old updater delivers it; every later update uses the new one.
- [ ] After updating: the plugin row shows **Enable auto-updates**, and **View details** shows the release notes with the Requirements block.
- [ ] Dashboard → Updates → **Check again** finds no update (current) without errors; `wp option get gf_tops_github_release --format=json` (or the `wp_options` / `wp_sitemeta` row) shows a stored ETag.
- [ ] Port the standard to Landing Page Manager, Landing Page Manager Pro and Radius SEO with the porting prompt (Pro blocked until its release delivery is decided; its repository is private).

## Deployment checks for 1.5.0 (review-board fixes)

- [ ] Request log: a user with only "edit forms" cannot open **Forms → TOPS log**; a user with "view entries" can view but sees no Resend button without "edit entries".
- [ ] Setup checklist appears on an enabled form's TOPS tab and updates after saving credentials, running **Test authentication**, mapping fields and adding a contact line.
- [ ] Model drop-down: choosing a make shows "Loading models…", then the models; with the API environment pointed at an unreachable custom URL, the drop-down keeps its placeholder and shows the "could not be loaded" message, and the checklist shows the failed lookup.
- [ ] Screen reader (or browser accessibility tree): the ⚡ marker reads "Choices provided by the dispatch system", not "high voltage".
- [ ] **Tools → Export Personal Data** for a test email used in a QA submission includes a "Tow request log" group; **Erase Personal Data** removes those log rows. **Settings → Privacy → Policy Guide** shows the suggested text.
- [ ] Resend a row on a form with an empty field map: the notice says nothing was sent.
- [ ] Before the first tag after merging: add required reviewers to the `release` environment and protect `v*` tags in GitHub settings (see owner decisions).

## Review board, 2026-10-14 (full-product review)

All six departments reviewed the whole product. Everything that did not need the owner was built in the `feature/review-board-fixes` branch (1.5.0). Owner decisions:

1. **Pricing model.** Finance recommends a free plugin plus paid setup and support. Selling the download or updates gives little protection under GPL with public releases, and needs a license server and private delivery. Validate demand first: ask TowXchange about a partner listing and its API terms; pilot pricing with WhiteRail; put up a "request setup" page and count inquiries.
2. **Product name.** Legal rates a name that starts with "Gravity Forms" as the biggest trademark risk for a commercial product. Recommend a neutral name plus "for Gravity Forms" (the folder slug can stay). Counsel review.
3. **Ownership and IP.** Confirm with counsel that the work account / OneDrive (WorldVia Travel Group) location creates no employer IP claim. Choose the copyright holder of record (Odell Duppins or Duppins Technology as an entity).
4. **Visitor error text.** Security recommends a generic message for visitors. This conflicts with the recorded decision to show TowX error text. Keep, or switch to generic text plus the contact line?
5. **Retention.** Legal, security and finance all recommend a configurable retention period (for example 30–90 days) instead of keeping log rows indefinitely, especially if the product is sold to more sites.
6. **Failure emails.** Send a link to the log row instead of the full request details (customer data)?
7. **Confidential names in public files.** `OWNERSHIP.md` (shipped in the ZIP) names WhiteRail; `docs/github-updater-standard.md` and this plan name other products, including that LPM Pro's repository is private. Keep public?
8. **Contacts and promises.** Security / support email address and response times for `SECURITY.md` and readme.txt.
9. **Default environment.** Default fresh installs to QA, so the first test does not create a real tow call?
10. **Site-wide credentials.** Offer one set of TowX credentials per site instead of per form?
11. **Custom API host.** Restrict the custom base URL to `*.towxchange.net` (currently HTTPS is enforced and other hosts get a warning)?
12. **GitHub settings.** Protect `v*` tags and add required reviewers to the `release` environment.
13. **TowX.** Ask whether credentials can be sent in headers instead of the query string, and whether a commercial third-party integration is allowed.
14. **Contributions.** Add a CLA or DCO if the licensing model may change later.
15. **Sample form.** Ship an importable sample Gravity Forms form (needs a Gravity Forms install to build and verify the export).

## Product decisions (2026-10-14)

- **Request-log retention:** rows are kept indefinitely (until the plugin is uninstalled). No scheduled cleanup.
- **Public error detail:** visitors keep seeing TowX / transport error text in the error confirmation so they can quote it when reporting a problem. Leaning decision; revisit if the text exposes anything sensitive. Sites can already replace it per form with the custom error message setting.
- **Front-end ⚡ marker:** kept on the public form's Make, Model and Color labels.

## Open items

- **Formatting standard:** WordPress whitespace / Yoda sniffs are off in `phpcs.xml.dist`. Enabling them means a one-time `phpcbf` reformat in its own PR.

## Completion notes

Record completed deployment checks with the release or pull request. Do not record secrets, live payloads, or personally identifiable customer data here.
