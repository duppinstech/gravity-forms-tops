# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

GitHub release notes are generated from the matching `= x.y.z =` section in [readme.txt](readme.txt). Update that section and this file together.

## [Unreleased]

### Added

- Plugins → View details shows the readme description, installation (including the setup guide), FAQ, and screenshots, plus the banner and icon. When the GitHub updater has release notes, the Changelog tab stays those notes. Settings still opens Forms → Settings → TOPS. There is no Help link. Support stays support@duppinstech.com. Sponsor is an optional link to the author's GitHub Sponsors page and is not required to use the plugin.
- Forms → Settings → TOPS → **API product**. The default is TOPSLink. A custom product is used only after it is saved. An upgrade does not write this setting, so existing sites keep sending TOPSLink until an administrator chooses otherwise.

## [1.5.0] - 2026-10-16

### Security

- The request log now requires permission to view Gravity Forms entries (resending requires permission to edit entries). A custom API address is always called over HTTPS, with a warning if it is not a TowX address. The API address is no longer printed in public page source.

### Fixed

- A malformed Make value in a submitted form no longer causes a PHP warning.

### Changed

- The Model drop-down shows "Loading models…" and a clear message if models cannot be loaded or none exist, instead of going blank. The message is announced to screen readers.
- New Setup checklist on each form's TOPS settings (credentials, connection test, field mapping, contact line, environment, and the last failed TowX lookup).
- Settings link on the Plugins screen; clearer field-map labels; the request log shows what actually happened when you resend.
- Suggested privacy-policy text, and request-log rows are included in Tools → Export / Erase Personal Data.
- When TowX fails, visitors see a generic message and the contact line instead of the technical error. Signed-in staff who can open the request log see the error and a link to the log; error emails link to the log too.
- Request-log rows are deleted after 90 days by default (Forms → Settings → TOPS → Keep request log for; 0 keeps them forever). Gravity Forms entries are not affected.
- Support address is support@duppinstech.com (also linked on the Plugins screen).
- When TowX is unreachable, vehicle lookups pause for about 90 seconds instead of slowing every page view.
- With LiteSpeed Cache, pages with the model drop-down are cached for at most 10 hours so the drop-down's security token never goes stale.
- The marker on TowX-filled fields has proper screen-reader text.
- Translation template (`languages/gravity-forms-tops.pot`) included.
- Signed build provenance and stricter release checks.

## [1.4.1]

### Changed

- Update checks now also run in the background, so new versions appear (and auto-updates work) without someone visiting wp-admin.
- The Enable auto-updates toggle works for this plugin.
- Only official release packages of this plugin are installed, and the update details show each release's own WordPress / PHP requirements.
- Sites that installed the plugin under a different folder name stay active after updating.
- Fewer requests to GitHub, and Dashboard → Updates → Check again re-checks right away.

## [1.4.0] - 2026-10-14

### Security

- TowX failure emails and the short-lived confirmation transient now contain the redacted Create Call XML. Earlier versions emailed the TowX password, Session ID and authentication key in plain text.
- Stored secrets are no longer decrypted into the form settings screen; they are decrypted only when calling TowX. "Test authentication" still uses the saved key when the field is left blank.
- Model lookups (AJAX and on submit) only accept make keys from TowX's GetMakes list, and submit-time lookups run only for a real submission of that form, so arbitrary requests cannot spend the site's TowX quota.
- On upgrade, any remaining plaintext TowX password / authentication key in form settings is encrypted automatically, including inactive and trashed forms (no need to re-save each form).

### Fixed

- VehicleInfo now sends the model name (not its TowX key) by loading models for the submitted make on the server. Re-rendering after a validation error keeps the visitor's model choice.
- Resend from the request log now uses make / model / color names in VehicleInfo (previously sent raw keys).
- Blank or partial addresses no longer produce values like ", ,  " in Location / Destination.
- Control characters pasted into form fields are stripped so they cannot make the Create Call XML invalid.
- The request-log table is created on first write, so submissions right after an upgrade are logged even before an admin opens wp-admin.

### Changed

- GetMakes, GetColors and GetModelsForMake results are cached for one hour (filter `gf_tops_vehicle_list_cache_ttl`, 0 disables). Previously every render, validation and submission called TowX.
- Resending a request-log row that already returned a Call ID asks for confirmation (a resend creates a second tow call).
- The `<!-- GF TOPS … -->` footer comment is shown only when browser console logging or WP_DEBUG is on (filter `gf_tops_show_footer_diagnostic_comment`).
- New filter `gf_tops_models_require_nonce` for sites that cannot exclude tow-form pages from long-lived page caches.
- Uninstall cleans up every site on multisite and removes cached plugin transients.
- GitHub updater, plugin URI and security contact now point to `duppinstech/gravity-forms-tops`.
- Composer dev tooling, PHPCS ruleset, PHPUnit tests, CI on PHP 7.4–8.3, and release checks that the tag matches the plugin version.

## [1.3.9] - 2026-05-08

### Fixed

- Removed the `upgrader_source_selection` hook that returned plugin paths without a trailing slash (WordPress core already picks the correct folder for single-root ZIPs). That mismatch caused "The package could not be installed" on many hosts.
- Set `Accept: application/octet-stream`, a consistent User-Agent, and a longer HTTP timeout when downloading `.zip` assets from GitHub / objects.githubusercontent.com.

## [1.3.8] - 2026-05-08

### Changed

- Confirmation `{organization}` and default "Thank you for contacting …" now use the WordPress Site Title (Settings → General → Site Title, option `blogname`) when the per-form override field is left blank.

## [1.3.7]

### Changed

- Per-form TowX confirmation text (Form Settings → TOPS → TowX confirmation text): optional override for the name shown in defaults and `{organization}` (otherwise uses Settings → General → Site Title), optional contact HTML when TowX fails (overrides global setting per form), and optional custom success/error/unclear templates with placeholders `{call_id}`, `{error}`, `{organization}`, `{site_name}`, `{contact_html}`.
- Default success intro thanks the visitor using the site title when no override is set; replaced generic "our team" follow-up with neutral copy.

## [1.3.6]

### Changed

- Run TowX Create Call on `gform_pre_handle_confirmation` (before the confirmation is built) instead of `gform_after_submission`, so the confirmation message can reflect success (Call ID), API error, or an ambiguous state.
- Richer default confirmation HTML for those outcomes; optional site-wide contact line when TowX fails under Forms → Settings → TOPS (shown on errors and ambiguous states; message confirmations only, not redirects).
- Error notification email: trim + `sanitize_email`; optional diagnostic line in the PHP error log when logging is enabled and the address is missing or invalid.
- PHPCS: document direct `$wpdb` use for the custom request-log table and uninstall `DROP TABLE`.

## [1.3.5] - 2026-05-08

### Security

- Encrypt TowX password and authentication key at rest in form meta using `GFCommon::openssl_encrypt` (same scheme as Gravity Forms). Existing plaintext values migrate to ciphertext the next time form settings are saved.

## [1.3.4]

### Changed

- Production readiness: Plugin Check fixes — translators comments for JS i18n strings with placeholders; escaped admin request-log output; documented nonce waiver for read-only GET navigation; `phpcs:ignore` on required `http_api_curl` options (no `wp_remote_*` equivalent for these TLS/SNI flags).
- Database: use `$wpdb->prepare()` identifier placeholder `%i` for the custom log table (requires WordPress 6.2+); `DROP TABLE` on uninstall uses the same pattern.
- `load_plugin_textdomain` PHPCS note for non–WordPress.org distribution.
- `languages/index.php` instead of a hidden placeholder file; readme `Tested up to` / tag count for WordPress.org rules.
- Minimum WordPress version raised to 6.2 (identifier placeholders).

## [1.3.3]

### Fixed

- Register Elementor HTML capture on `elementor/loaded` (not only `wp`) so `gform_wrapper_*` IDs are recorded before `wp_footer` enqueue.
- Footer HTML comment `<!-- GF TOPS … -->` lists discovered form IDs, integration on/off, and whether `gf-tops-frontend` enqueued (View Source → search "GF TOPS").

## [1.3.2]

### Fixed

- Discover Gravity Forms IDs from rendered HTML (`gform_wrapper_{id}`) via late `the_content`, widgets, `render_block`, and Elementor `elementor/frontend/the_content` — fixes missing scripts when page builders never expose shortcodes in raw post_content.

## [1.3.1]

### Fixed

- Discover Gravity Forms on singular pages using `get_queried_object_id()` (fixes empty discovery when `global $post` is unset during `wp_enqueue_scripts`).
- Scan Elementor `_elementor_data` JSON for form IDs / embedded shortcodes.
- `wp_footer` fallback enqueue if TOPS assets were not queued earlier.

## [1.3.0]

### Added

- Front-end status callout when TOPS is enabled (API environment, makes/colors counts, model cascade status); DOM recount after Gravity Forms renders.
- Enqueue TOPS scripts via `wp_enqueue_scripts` when form IDs are found in post shortcodes/blocks (fallback when `gform_enqueue_scripts` does not run).
- Filter `gf_tops_discover_form_ids` for page builders that embed forms outside post_content.
- Cascade diagnostics optional UI; hidden-field sync options; improved GetModelsForMake error handling.

## [1.2.0]

### Added

- Request log: each Create Call attempt is stored (redacted request XML, raw response, HTTP code, Call ID when TowX returns CallKey).
- Forms → TOPS settings: link to the log; Forms → TOPS log submenu lists attempts per form with pagination.
- Resend Create Call from the log (rebuilds XML from the current entry and saved credentials; does not re-trigger confirmation email notifications).

## [1.1.0]

### Changed

- Mask password and authentication key in UI; preserve on save when left blank.
- Create Call XML uses the same API environment as JSON (production / QA / custom); removed per-form Create Call URL.
- VehicleInfo XML value built from Year, Make, Model, and Color mappings (labels when available).
- Admin authentication test (GetMakes) with detailed diagnostics on failure or unexpected responses.
- Repository layout: `includes/`, `assets/`, `languages/`, legal and security docs.

## [1.0.0]

### Added

- Initial release.

[1.5.0]: https://github.com/duppinstech/gravity-forms-tops/releases/tag/v1.5.0
[1.4.0]: https://github.com/duppinstech/gravity-forms-tops/releases/tag/v1.4.0
[1.3.9]: https://github.com/duppinstech/gravity-forms-tops/releases/tag/v1.3.9
[1.3.8]: https://github.com/duppinstech/gravity-forms-tops/releases/tag/v1.3.8
[1.3.5]: https://github.com/duppinstech/gravity-forms-tops/releases/tag/v1.3.5
