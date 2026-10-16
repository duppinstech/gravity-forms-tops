# SDLC and Deployment Plan

This file tracks follow-up work for the plugin. Check current code and workflow state before starting an item; the list is not an instruction to broaden unrelated changes.

## SDLC follow-ups

- [x] Choose a supported PHP lint / WordPress coding-standard setup and document reproducible local and CI commands. *(1.4.0: dev-only Composer; `composer lint`, `composer phpcs`, `composer test`; ruleset in `phpcs.xml.dist`; documented in README and CONTRIBUTING.)*
- [x] Add automated tests for credential encryption and migration, XML generation, and request/response handling without storing real credentials or customer data. *(1.4.0: `tests/SecretsTest.php`, `tests/XmlTest.php`, `tests/EntryTest.php`; fake values only.)*
- [x] Add a CI validation workflow after the supported checks and test environment are agreed. *(1.4.0: `.github/workflows/ci.yml`, PHP 7.4–8.3.)*
- [x] Review the release ZIP exclusion list when adding development-only files or new top-level directories. *(1.4.0: packaging moved to `bin/build-zip.sh`, built from tracked files only; release workflow checks tag vs. version and re-runs lint/tests.)*

## Deployment checks for 1.4.0 (need a WordPress environment)

> 1.4.0 was tagged and released on 2026-10-14 before these checks were run. **Production (weilwrecker.com, multisite site 1) was upgraded 1.3.5 → 1.4.0 on 2026-10-16** with WP-CLI from the verified release ZIP (SHA-256 checked), after a full database and plugin backup in `/root/backups/` on the server. Afterwards: upgrade routine ran (stored version 1.4.0), form 6 integration enabled, password and key encrypted, 12 mappings intact, tow page renders with Makes from TowX, no errors. 1.3.5 never received updates because its updater only ran in wp-admin and the site uses system cron (`DISABLE_WP_CRON`).
>
> Staging (Docker: WordPress 7.0.7, PHP 8.3, Gravity Forms 2.10.2 — production versions; copy of form 6 definition only, no entries): TowX **QA** (`apiqa.towxchange.net`) fails the TLS handshake from every network (TowX-side certificate/SNI problem), so lookups were tested read-only against the production API and every Create Call was intercepted by a staging-only guard plugin (no real calls sent).

These need WordPress, a licensed Gravity Forms install and authorized QA TowX credentials, so they cannot run in CI.

- [x] **Install / upgrade:** on a staging copy of a production site (WordPress 6.2+, PHP 7.4+, Gravity Forms 2.5+), upgrade from 1.3.9 to 1.4.0. Confirm no PHP notices in `debug.log` and that Forms → TOPS log still lists earlier rows. *Staging 2026-10-16: 1.4.0 → 1.5.0 upgrade, no PHP notices; production 1.3.5 → 1.4.0 clean.*
- [x] **Secret migration:** on a form that still had plaintext credentials, confirm (DB: `wp_gf_form_meta`) the password and key now start with `gf_tops_e1:` after the upgrade, without re-saving the form. *Production: password and key stored as `gf_tops_e1:` ciphertext.*
- [ ] **Settings screen:** view page source of Form → Settings → TOPS; the password / authentication key must not appear in plaintext. Leave both blank, save, and confirm they are kept (Test authentication still succeeds).
- [ ] **Test authentication** against QA (Forms → Settings → TOPS → API environment = QA). *Blocked: TowX QA endpoint TLS failure (TowX-side).*
- [x] **Dropdowns:** Make and Color fill; choosing a make loads models; submit with a validation error and confirm the chosen model is still selected. *Staging 2026-10-16: 648 makes, 25 colors, 67 Toyota models via the page and admin-ajax (real TowX data). Validation-error re-render not exercised in a browser.*
- [x] **Create Call (QA):** submit a test request; confirm the Call ID in the confirmation and in the request log, and that the logged XML shows `***` for Password / SessionID / AuthenticationKey. *Staging 2026-10-16: simulated CallKey; Call ID in confirmation and log; UserID / Password / SessionID / AuthenticationKey all `***`.*
- [x] **VehicleInfo:** in the request log row, VehicleInfo shows names (for example `2020 Toyota Camry Blue`), not numeric keys. Repeat with **Resend Create Call**; the resend must also show names, and resending a successful row must ask for confirmation. *Staging 2026-10-16: "2020 Toyota 2000 Beige" (names, not keys), also on resend.*
- [x] **Error email:** set the API environment to an invalid custom URL, submit, and confirm the email contains no password, session ID or authentication key. Restore the environment. *Staging 2026-10-16: captured email links to the log and contains no credential.*
- [ ] **Non-ASCII text:** submit with an accented name and a curly apostrophe (for example `José O’Brien`) and confirm how it appears in TowX. The plugin sends UTF-8 bytes but labels the request `charset=WINDOWS-1252`; if TowX shows garbled characters, open a fix to transcode to Windows-1252 (or change the declared charset) after confirming which one TowX expects. *Still open: needs a real Create Call to see how TowX displays it.*
- [x] **Page cache:** if the site uses a full-page cache, exclude tow-form pages or confirm their cache lifetime is under 12 hours (see README → Production notes). *Production uses LiteSpeed with a 7-day TTL; 1.5.0 caps pages with the model drop-down at 10 hours automatically.*
- [x] **Release:** `v1.4.0` tagged on `main` and the release ZIP published (2026-10-14).
- [x] **Credential rotation:** not needed (confirmed by the owner, 2026-10-14).

## Deployment checks for 1.4.1 (updater standard)

- [ ] On a site running 1.4.0, update to 1.4.1 from Plugins → Updates. 1.4.0's old updater delivers it; every later update uses the new one.
- [ ] After updating: the plugin row shows **Enable auto-updates**, and **View details** shows the release notes with the Requirements block.
- [ ] Dashboard → Updates → **Check again** finds no update (current) without errors; `wp option get gf_tops_github_release --format=json` (or the `wp_options` / `wp_sitemeta` row) shows a stored ETag.
- [x] Updater on staging (real GitHub API, cron-style context): listed as current in `no_update`, release + ETag cached for 6 h, repeat check sent `If-None-Match` and got HTTP 304. *Staging 2026-10-16*
- [ ] Port the standard to Landing Page Manager, Landing Page Manager Pro and Radius SEO with the porting prompt (Pro blocked until its release delivery is decided; its repository is private).

## Deployment checks for 1.5.0 (review-board fixes)

- [x] Request log: a user with only "edit forms" cannot open **Forms → TOPS log**; a user with "view entries" can view but sees no Resend button without "edit entries". *Staging 2026-10-16 (capability checks).*
- [x] Setup checklist appears on an enabled form's TOPS tab and updates after saving credentials, running **Test authentication**, mapping fields and adding a contact line. *Staging 2026-10-16 (checklist content; screen rendering not viewed).*
- [ ] Model drop-down: choosing a make shows "Loading models…", then the models; with the API environment pointed at an unreachable custom URL, the drop-down keeps its placeholder and shows the "could not be loaded" message, and the checklist shows the failed lookup.
- [ ] Screen reader (or browser accessibility tree): the ⚡ marker reads "Choices provided by the dispatch system", not "high voltage".
- [x] **Tools → Export Personal Data** for a test email used in a QA submission includes a "Tow request log" group; **Erase Personal Data** removes those log rows. **Settings → Privacy → Policy Guide** shows the suggested text. *Staging 2026-10-16 (exporter / eraser callbacks; WordPress tools screens not viewed). Note: production form 6 has no email field, so export / erase cannot match its rows by email.*
- [ ] Resend a row on a form with an empty field map: the notice says nothing was sent.
- [x] Upgrade 1.4.1 → 1.5.0: no notices in `debug.log`; existing log rows are kept. *Staging 2026-10-16 (from 1.4.0).*
- [x] Erasure with similar emails: submit as `bob@example.test` and as `jimbob@example.test` (in a mapped Customer Email field); erasing `bob@example.test` removes only bob's rows, and the eraser message explains rows without that email cannot be matched. *Staging 2026-10-16: bob@ erased, jimbob@ kept, eraser message present.*
- [ ] Custom base URL `http://…`: both warnings show and the log row's endpoint starts with `https://`. View source on the form page: no API address while console logging is off.
- [ ] Unreachable custom URL: the second page load is fast (lookups paused) and the checklist shows the failure. Choosing a make TowX rejects only affects that make.
- [ ] Changing a saved credential clears the "Connection test passed" tick until the next successful test.
- [x] Retention: with **Keep request log for** = 1, a log row backdated more than a day in the database is gone after the daily `gf_tops_purge_request_log` cron run (`wp cron event run gf_tops_purge_request_log`); with 0, nothing is deleted. Deactivating the plugin removes the cron event. *Staging 2026-10-16: 0 keeps a 120-day-old row; default 90 deletes it; cron event scheduled 24 h after upgrade.*
- [x] TowX failure seen by a logged-out visitor: generic message and contact line, no technical detail. Same form submitted while logged in as an admin: the staff-only note shows the error and links to the log. The error email links to the log. *Staging 2026-10-16: generic text + contact line for visitors; staff note with error and log link for admins; email links to the log.*
- [ ] Plugins screen shows a **Settings** link and a **Support** link (support@duppinstech.com). Multisite: uninstall removes `gf_tops_lookup_status` and `gf_tops_auth_test_ok` on every site.
- [ ] On QA, confirm the TowX endpoints answer without redirects; if so, consider turning redirects off for TowX calls (`'redirection' => 0`) so a redirect can never downgrade to HTTP.
- [x] Before the first tag after merging: add required reviewers to the `release` environment and protect `v*` tags in GitHub settings (see owner decisions). *v* tags protected by ruleset (2026-10-14); release-environment reviewers optional (owner).*

## Review board, 2026-10-14 (full-product review)

All six departments reviewed the whole product. Everything that did not need the owner was built in the `feature/review-board-fixes` branch (1.5.0).

**Resolved by the owner (2026-10-14), built in 1.5.0:**

- **Sales model:** payment is taken before the plugin is delivered or installed; no license keys in the plugin. Note: releases stay public on GitHub, so anyone can still download the code (GPL); payment buys delivery, setup and support.
- **Copyright holder:** Duppins Technology (Odell Duppins is the author). LICENSE, README and OWNERSHIP.md updated.
- **Visitor error text:** generic message plus the contact line; signed-in staff who can open the log see the real error and a link to the log; error emails link to the log.
- **Retention:** request-log rows deleted after 90 days by default (configurable; 0 keeps forever). Gravity Forms entries are never deleted by this plugin.
- **Beta client name:** `OWNERSHIP.md` stays in the repository but is no longer shipped in the release ZIP.
- **Support / security contact:** support@duppinstech.com (readme, SECURITY.md, Plugins-screen Support link, translation bug reports). No response-time promise.
- **Tag protection:** repository ruleset "Protect release tags" on `refs/tags/v*` (admins only create; no update or delete) — set via the API.

**Still open:**

1. **Product name.** Legal rates a name that starts with "Gravity Forms" as the biggest trademark risk for a commercial product. Recommend a neutral name plus "for Gravity Forms" (the folder slug can stay). Counsel review.
2. **Ownership and IP (counsel).** Confirm that the work account / OneDrive (WorldVia Travel Group) location creates no employer IP claim. If Duppins Technology is a separate legal entity, it needs a written IP assignment from Odell Duppins to hold the copyright; if it is a trade name, Odell is the legal holder. Also get the beta client's written OK to be named in the public repository (removing it from the ZIP does not remove it from GitHub or git history).
2a. **Terms of sale (counsel).** Payment buys delivery, setup and support, not the code (GPL lets buyers redistribute it, and public releases can be installed without paying). Put what payment covers, support scope, a warranty disclaimer consistent with GPL "AS IS", and refunds in the client agreement. Confirm TowX's API terms allow commercial third-party use (item 11).
3. **Release approvals.** Add required reviewers to the `release` environment (GitHub creates it on the first release run). Optional for a solo maintainer now that tags are protected.
4. **Failure emails.** Keep the full request details in the email, or send only the log link?
5. **Other products named in public docs.** `docs/github-updater-standard.md` and this plan mention other products, including that LPM Pro's repository is private.
6. **Default environment.** Default fresh installs to QA, so the first test does not create a real tow call?
7. **Site-wide credentials.** Offer one set of TowX credentials per site instead of per form?
8. **Custom API host.** Restrict the custom base URL to `*.towxchange.net` (currently HTTPS is enforced and other hosts get a warning)?
9. **Release verification.** Build provenance is attested, but the in-plugin updater does not verify it. Document offline verification (`gh attestation verify gravity-forms-tops.zip -R duppinstech/gravity-forms-tops`) for now, or add checksum verification to the updater standard later?
10. **Visitor message when models cannot load.** Currently generic ("Choose the make again to retry"). Product suggests adding the form's contact line, or owner-set text, so visitors with a required Model field are never stuck.
11. **TowX.** Ask whether credentials can be sent in headers instead of the query string, and whether a commercial third-party integration is allowed.
12. **Contributions.** Add a CLA or DCO if the licensing model may change later.
13. **Sample form.** Ship an importable sample Gravity Forms form (needs a Gravity Forms install to build and verify the export).

## Product decisions (2026-10-14)

- **Request-log retention:** 90 days by default (configurable; 0 keeps forever). Supersedes the earlier "keep indefinitely".
- **Public error detail:** generic text for visitors; details for staff and in the log and email. Supersedes the earlier "show TowX error text".
- **Front-end ⚡ marker:** kept on the public form's Make, Model and Color labels.

## Open items

- **Formatting standard:** WordPress whitespace / Yoda sniffs are off in `phpcs.xml.dist`. Enabling them means a one-time `phpcbf` reformat in its own PR.

## Completion notes

Record completed deployment checks with the release or pull request. Do not record secrets, live payloads, or personally identifiable customer data here.
