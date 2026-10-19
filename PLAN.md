# SDLC and Deployment Plan

This file tracks follow-up work for the plugin. Check current code and workflow state before starting an item; the list is not an instruction to broaden unrelated changes.

## Where this stands (2026-10-18)

The 1.4.0 and 1.5.0 behaviors below are in the plugin. Local Docker (`gravity-forms-tops-local`, http://127.0.0.1:8095, WordPress 6.8.3, PHP 8.2.29, Gravity Forms 2.10.2) covered the admin screens on 2026-10-19. Unchecked rows still need TowX QA, a multisite uninstall, another repository, or counsel. TowX QA is down (owner, 2026-10-18); only Production answers. The API product defaults to TOPSLink and is not written on upgrade. Distribution stays public under GPL.

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

- [ ] **Install / upgrade:** on a staging copy of a production site (WordPress 6.2+, PHP 7.4+, Gravity Forms 2.5+), upgrade from 1.3.9 to 1.4.0. Confirm no PHP notices in `debug.log` and that Forms → TOPS log still lists earlier rows. *Staging 2026-10-16: 1.4.0 → 1.5.0 upgrade, no PHP notices; production 1.3.5 → 1.4.0 clean.* *Partly verified: the evidence covers 1.3.5 → 1.4.0 (production) and 1.4.0 → 1.5.0 (staging), not 1.3.9.*
- [x] **Secret migration:** on a form that still had plaintext credentials, confirm (DB: `wp_gf_form_meta`) the password and key now start with `gf_tops_e1:` after the upgrade, without re-saving the form. *Production: password and key stored as `gf_tops_e1:` ciphertext.*
- [x] **Settings screen:** view page source of Form → Settings → TOPS; the password / authentication key must not appear in plaintext. Leave both blank, save, and confirm they are kept (Test authentication still succeeds). *Local Docker 2026-10-19: plaintext password and key were absent from the page. The masked fields hold the stored `gf_tops_e1:` ciphertext. Saving with both left blank kept the decrypted values. A live Test authentication was not repeated here; QA is still down and this site's custom host does not resolve.*
- [ ] **Test authentication** against QA (Forms → Settings → TOPS → API environment = QA). *Passed against Production on 2026-10-16 (648 makes).* *Blocked: owner confirmed 2026-10-18 that QA is down and only Production works.*
- [x] **Dropdowns:** Make and Color fill; choosing a make loads models; submit with a validation error and confirm the chosen model is still selected. *Staging 2026-10-16: 648 makes, 25 colors, 67 Toyota models via the page and admin-ajax (real TowX data).* *Local Docker 2026-10-19: with TowX unreachable, a validation error on Year re-rendered the form with Camry still selected.*
- [ ] **Create Call (QA):** submit a test request; confirm the Call ID in the confirmation and in the request log, and that the logged XML shows `***` for Password / SessionID / AuthenticationKey. *Staging 2026-10-16: simulated CallKey; Call ID in confirmation and log; UserID / Password / SessionID / AuthenticationKey all `***`.* *Blocked: QA still down (owner, 2026-10-18). A Production Create Call would create a real tow. Remaining: one real QA Create Call when TowX restores QA.*
- [x] **VehicleInfo:** in the request log row, VehicleInfo shows names (for example `2020 Toyota Camry Blue`), not numeric keys. Repeat with **Resend Create Call**; the resend must also show names, and resending a successful row must ask for confirmation. *Staging 2026-10-16: "2020 Toyota 2000 Beige" (names, not keys), also on resend.*
- [x] **Error email:** set the API environment to an invalid custom URL, submit, and confirm the email contains no password, session ID or authentication key. Restore the environment. *Staging 2026-10-16: captured email links to the log and contains no credential.*
- [ ] **Non-ASCII text:** submit with an accented name and a curly apostrophe (for example `José O’Brien`) and confirm how it appears in TowX. The plugin sends UTF-8 bytes but labels the request `charset=WINDOWS-1252`; if TowX shows garbled characters, open a fix to transcode to Windows-1252 (or change the declared charset) after confirming which one TowX expects. *Still open: needs a real Create Call to see how TowX displays it.*
- [x] **Page cache:** Production uses LiteSpeed (7-day TTL). Since 1.5.0 on production (2026-10-16), `/request-a-tow/` is served with `x-litespeed-cache-control: public,max-age=36000`; other pages keep 7 days. *Verified on production 2026-10-16.*
- [x] **Release:** `v1.4.0` tagged on `main` and the release ZIP published (2026-10-14).
- [x] **Credential rotation:** not needed (confirmed by the owner, 2026-10-14).

## Deployment checks for 1.4.1 (updater standard)

- [ ] On a site running 1.4.0, update to 1.4.1 from Plugins → Updates. 1.4.0's old updater delivers it; every later update uses the new one.
- [x] After updating: the plugin row shows **Enable auto-updates**, and **View details** shows the release notes with the Requirements block. *Local Docker 2026-10-19 on 1.5.0: Enable auto-updates, Settings, and Support (`mailto:support@duppinstech.com`) are on the row. View details includes Requires WordPress 6.2, Requires PHP 7.4, and the Requirements block. This was a fresh 1.5.0 install, not an update from 1.4.0.*
- [x] Dashboard → Updates → **Check again** finds no update (current) without errors; `wp option get gf_tops_github_release --format=json` (or the `wp_options` / `wp_sitemeta` row) shows a stored ETag. *Local Docker 2026-10-19: Check again listed no update for this plugin (installed 1.5.0, GitHub latest 1.5.0) and stored an ETag. Akismet and the default themes were the only updates offered.*
- [x] Updater on staging (real GitHub API, cron-style context): listed as current in `no_update`, release + ETag cached for 6 h, repeat check sent `If-None-Match` and got HTTP 304. *Staging 2026-10-16*
- [ ] Port this updater standard into the other Duppins Technology plugins that copy it. Do that work in those repositories.

## Deployment checks for 1.5.0 (review-board fixes)

> **Production (weilwrecker.com) upgraded 1.4.0 → 1.5.0 on 2026-10-16** with WP-CLI from the release ZIP (SHA-256 matched; build provenance attested), after a database + plugin backup in `/root/backups/gf-tops-1.4.0-to-1.5.0-20261016T043245Z/`. Verified: stored version 1.5.0, log cleanup scheduled 24 h out (90-day retention), form 6 enabled with 12 mappings and encrypted password / key, tow page renders with TowX makes, LiteSpeed 10-hour cap on the tow page, and the **new updater works from a background (cron) check**: plugin listed as current with release 1.5.0 and its ETag cached, so future releases arrive without anyone visiting wp-admin. Note: the first render right after installing can still run the old code for up to 60 s (opcache `revalidate_freq=60`); purge the page cache after an install, as was done here.
>
> Setup checklist on production form 6: **Test authentication** passed against Production (648 makes, 2026-10-16) and is recorded. Remaining: **no contact line** is set for visitors when TowX fails (owner to add a phone number / link under Form → Settings → TOPS → TowX confirmation text).

- [x] Request log: a user with only "edit forms" cannot open **Forms → TOPS log**; a user with "view entries" can view but sees no Resend button without "edit entries". *Staging 2026-10-16 (capability checks).*
- [x] Setup checklist appears on an enabled form's TOPS tab and updates after saving credentials, running **Test authentication**, mapping fields and adding a contact line. *Local Docker 2026-10-19: the TOPS tab showed the checklist, including saved credentials, a passed connection-test line, mapped fields, the contact line, API product TOPSLink, and the failed lookup.*
- [x] Model drop-down: choosing a make shows "Loading models…", then the models; with the API environment pointed at an unreachable custom URL, the drop-down keeps its placeholder and shows the "could not be loaded" message, and the checklist shows the failed lookup. *Local Docker 2026-10-19: choosing Toyota showed "Loading models…" and then "Vehicle models could not be loaded. Choose the make again to retry." The checklist showed the GetMakes failure. Real TowX model lists were not loaded (QA is down).*
- [x] Screen reader (or browser accessibility tree): the ⚡ marker reads "Choices provided by the dispatch system", not "high voltage". *Local Docker 2026-10-19: Make, Model and Color names included "Choices provided by the dispatch system."*
- [x] **Tools → Export Personal Data** for a test email used in a QA submission includes a "Tow request log" group; **Erase Personal Data** removes those log rows. **Settings → Privacy → Policy Guide** shows the suggested text. *Local Docker 2026-10-19: Policy Guide shows the plugin's suggested text. Export for local-check@example.test included the "Tow request log" group, and Erase personal data completed. This used a local fixture email, not a QA submission. Production form 6 still has no email field.*
- [x] Resend a row on a form with an empty field map: the notice says nothing was sent. *Local Docker 2026-10-19: "Nothing was sent: this form has no TOPS field map. Map the fields under Form Settings → TOPS, then resend."*
- [x] Upgrade 1.4.0 → 1.5.0 (1.4.1 was never released on its own; it ships inside 1.5.0): no notices in `debug.log`; existing log rows are kept. *Staging 2026-10-16 (from 1.4.0).*
- [x] Erasure with similar emails: submit as `bob@example.test` and as `jimbob@example.test` (in a mapped Customer Email field); erasing `bob@example.test` removes only bob's rows, and the eraser message explains rows without that email cannot be matched. *Staging 2026-10-16: bob@ erased, jimbob@ kept, eraser message present.*
- [x] Custom base URL `http://…`: both warnings show and the log row's endpoint starts with `https://`. View source on the form page: no API address while console logging is off. *Local Docker 2026-10-19: Forms → Settings → TOPS showed both warnings for `http://towx.invalid/v1/`. The logged Create Call URL was `https://towx.invalid/v1/?Product=TOPSLink`. The public form source did not contain that host or `towxchange`. No real tow was created.*
- [x] Unreachable custom URL: the second page load is fast (lookups paused) and the checklist shows the failure. Choosing a make TowX rejects only affects that make. *Local Docker 2026-10-19: after the first DNS failure, later page loads stayed under a tenth of a second and did not record a new failure. The checklist showed the failed lookup. A TowX rejection of one make was not exercised; this host never answered.*
- [x] Changing a saved credential clears the "Connection test passed" tick until the next successful test. *Local Docker 2026-10-19: changing the User ID and saving cleared the tick. The password and key were left blank and kept.*
- [x] Retention: with **Keep request log for** = 1, a log row backdated more than a day in the database is gone after the daily `gf_tops_purge_request_log` cron run (`wp cron event run gf_tops_purge_request_log`); with 0, nothing is deleted. Deactivating the plugin removes the cron event. *Local Docker 2026-10-19: running the `gf_tops_purge_request_log` hook with retention 1 deleted a 3-day-old row and kept a row from the same day; retention 0 kept a 10-day-old row. Deactivating removed the cron event, and the next page load scheduled it again. Retention was set back to 90.*
- [x] TowX failure seen by a logged-out visitor: generic message and contact line, no technical detail. Same form submitted while logged in as an admin: the staff-only note shows the error and links to the log. The error email links to the log. *Staging 2026-10-16: generic text + contact line for visitors; staff note with error and log link for admins; email links to the log.*
- [ ] Plugins screen shows a **Settings** link and a **Support** link (support@duppinstech.com). Multisite: uninstall removes `gf_tops_lookup_status` and `gf_tops_auth_test_ok` on every site. *Local Docker 2026-10-19: the Plugins row has Settings (Forms → Settings → TOPS) and Support (`mailto:support@duppinstech.com`). Remaining: run uninstall on a multisite. This local site is single-site.*
- [ ] On QA, confirm the TowX endpoints answer without redirects; if so, consider turning redirects off for TowX calls (`'redirection' => 0`) so a redirect can never downgrade to HTTP. *2026-10-18: QA still fails the TLS handshake. Owner confirmed only Production works. Production `GetMakes` and Create Call URLs returned HTTP 200 with no `Location` header. Redirects stay on until QA answers.*
- [x] Before the first tag after merging: add required reviewers to the `release` environment and protect `v*` tags in GitHub settings (see owner decisions). *v* tags protected by ruleset (2026-10-14); release-environment reviewers optional (owner).*

## Review board, 2026-10-14 (full-product review)

All six departments reviewed the whole product. Everything that did not need the owner was built in the `feature/review-board-fixes` branch (1.5.0).

**Resolved by the owner (2026-10-14), built in 1.5.0:**

- **Sales model:** payment is taken before the plugin is delivered or installed; no license keys in the plugin. Note: releases stay public on GitHub, so anyone can still download the code (GPL); payment buys delivery, setup and support. The 2026-10-18 private / not-for-sale note was withdrawn the same day.
- **Copyright holder:** Duppins Technology (Odell Duppins is the author). LICENSE, README and OWNERSHIP.md updated.
- **Visitor error text:** generic message plus the contact line; signed-in staff who can open the log see the real error and a link to the log; error emails link to the log.
- **Retention:** request-log rows deleted after 90 days by default (configurable; 0 keeps forever). Gravity Forms entries are never deleted by this plugin.
- **Beta client name:** `OWNERSHIP.md` stays in the repository but is no longer shipped in the release ZIP.
- **Support / security contact:** support@duppinstech.com (readme, SECURITY.md, Plugins-screen Support link, translation bug reports). No response-time promise.
- **Tag protection:** repository ruleset "Protect release tags" on `refs/tags/v*` (admins only create; no update or delete) — set via the API.

**Decided 2026-10-18:**

- The plugin stays a generic TOPS integration. Distribution stays the 2026-10-14 public GPL model. The private-repository and not-for-sale notes from earlier the same day are withdrawn.
- **API product** on Forms → Settings → TOPS defaults to TOPSLink. A custom product is used only after an administrator saves it. Upgrade does not write the setting.
- Do not default new installs to QA. QA is down; Production is the only working TowX environment.
- The client is not named in this repository.
- **Release approvals.** Optional while one person maintains the plugin and `v*` tags are protected. Add required reviewers to the `release` environment if a second maintainer joins.

**Decided 2026-10-19:**

- **Product name:** GF TowX Integration. It connects a Gravity Forms form to TOPS by TowXchange. Duppins Technology is not affiliated with Rocketgenius or TowXchange. The folder slug, text domain, and saved settings stay `gravity-forms-tops`.
- **Employer IP location:** the owner closed this without a counsel review. Copyright stays Odell Duppins Jr, doing business as Duppins Technology.
- **Terms:** the plugin is provided AS IS. Payment, when charged, is for delivery, setup and support, not for the code. Refund terms are not stated.
- **TowX API:** commercial use is for TowXchange customers who already have an agreement and API credentials from TowXchange. Credentials stay on the request, which is how TowX documents them. They stay per form. A custom API host keeps the warning and is not limited to `*.towxchange.net`.
- **Failure emails:** keep the redacted request and the log link.
- **Model-load message:** keep "Choose the make again to retry."
- **Contributions:** Developer Certificate of Origin 1.1 (`Signed-off-by`). No contributor license agreement.
- **Other products:** this repository's docs name GF TowX Integration only.

- **Sample form:** do not ship one.
- **Refunds:** leave them unstated.

**Still open:** none of the owner questions above. Deployment checks that need TowX QA, a historical upgrade, or another repository remain in the lists above.

## Product decisions (2026-10-14)

- **Request-log retention:** 90 days by default (configurable; 0 keeps forever). Supersedes the earlier "keep indefinitely".
- **Public error detail:** generic text for visitors; details for staff and in the log and email. Supersedes the earlier "show TowX error text".
- **Front-end ⚡ marker:** kept on the public form's Make, Model and Color labels.

## Product decisions (2026-10-18)

- **API product:** TOPSLink unless an administrator saves a custom product under Forms → Settings → TOPS. Upgrade does not write or replace that setting.
- **QA is down.** Only Production (`https://api.towxchange.net/v1/`) is working. Do not default new installs to QA.
- **Distribution:** stays public under GPL. The private-repository and not-for-sale direction was withdrawn the same day.

## Open items

- **Formatting standard:** WordPress whitespace / Yoda sniffs are off in `phpcs.xml.dist`. Enabling them means a one-time `phpcbf` reformat in its own PR.
- **Updater checksums:** after the shared updater standard adds checksum or attestation checks, copy that class in here. Offline `gh attestation verify` covers releases until then.

## Completion notes

Record completed deployment checks with the release or pull request. Do not record secrets, live payloads, or personally identifiable customer data here.
