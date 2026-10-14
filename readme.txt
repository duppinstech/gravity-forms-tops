=== Gravity Forms TOPS / TowX Integration ===
Contributors: duppinstechnology
Tags: gravity forms, tops, towx, towing, api
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send Gravity Forms tow and roadside requests straight into TowX / TOPS dispatch, with live vehicle make, model and color lists.

== Description ==

This plugin connects a Gravity Forms request form on your WordPress site to your TowX / TOPS (TOPSLink) dispatch system:

* **Creates the call in dispatch.** Each submitted request is sent to TowX as a **Create Call**, and the visitor's confirmation shows the TowX Call ID.
* **Live vehicle lists.** Make and Color drop-downs are filled from TowX, and choosing a make loads that make's models.
* **Your form, your fields.** You map your own form fields to TowX fields; nothing is hard-coded.
* **Request log.** Every attempt is recorded under **Forms → TOPS log** with the response from TowX, and failed requests can be resent after you fix the cause.
* **Safe credentials.** The TowX password and authentication key are encrypted in the database and never shown again after saving.
* **Setup checklist and connection test** on each form's TOPS settings.
* **Privacy tools.** Suggested privacy-policy text, and request-log rows that can be matched to a person's email address are included in WordPress's personal-data export and erase tools.

**Requirements:** WordPress 6.2+, PHP 7.4+, Gravity Forms 2.5+ (licensed), and TowX / TOPS API credentials from TowXchange.

This plugin is not affiliated with or endorsed by Rocketgenius (Gravity Forms) or TowXchange (TowX / TOPS). Product names are used only to describe compatibility.

== Installation ==

1. Install `gravity-forms-tops.zip` from **Plugins → Add New → Upload Plugin**, or upload the `gravity-forms-tops` folder to `/wp-content/plugins/`.
2. Activate **Gravity Forms TOPS / TowX Integration**.
3. Follow the setup guide below.

= Setup guide =

1. **Get TowX API credentials.** Ask TowXchange (or your TOPS administrator) for API access. You need four values: **User ID**, **Password**, **Session ID** and **Authentication key**. Ask for **QA (test) access** as well, so you can test without creating real tow calls.
2. **Choose the environment.** Go to **Forms → Settings → TOPS** and set **API environment** to **QA** while you test. Optionally enter an **Error notification email** and a **contact line** shown to visitors if TowX cannot take a request (for example your dispatch phone number).
3. **Build the form.** Your request form needs fields for: pickup location and destination (Address, Single Line Text or Paragraph), caller name (Name or Text), caller phone (Phone or Text), tag number and tag state, vehicle year, and **Make, Model and Color as Drop Down fields** (the plugin fills their choices from TowX). Optional: dispatch notes, bill-to account, customer email, owner name, key location and call reason are added to the dispatch notes.
4. **Enable and connect.** Open the form → **Settings → TOPS**. Tick **Enable integration**, enter the four credentials, click **Save settings**, then **Test authentication**. It should report the number of vehicle makes found.
5. **Map the fields.** Under **Field map**, choose the form field for each TowX field and save. Once the integration is enabled and saved, the **Setup checklist** at the top of the tab shows what is still missing.
6. **Use a message confirmation.** The TowX result (Call ID, or the error and your contact line) is added to Gravity Forms **Text** confirmations. Redirect confirmations do not show it.
7. **Test on QA.** Submit the form. You should see a Call ID in the confirmation and a **Success** row under **Forms → TOPS log**.
8. **Go live.** Switch **API environment** to **Production**. From then on, every submission creates a real tow call.

= Page caching =

The model drop-down uses a security token that expires after 12–24 hours. Exclude tow-request pages from full-page caching, or cache them for less than 12 hours. Developers who cannot do either can use the `gf_tops_models_require_nonce` filter.

== Frequently Asked Questions ==

= The Make and Color drop-downs are empty or show my own placeholder choices =

Run **Test authentication** on the form's TOPS settings. Check that Make and Color are **Drop Down** fields mapped in the **Field map**, and that the **API environment** matches your credentials (QA credentials do not work in Production). If TowX is unreachable, the **Setup checklist** shows the last failed lookup, and the form keeps its saved choices until TowX answers again.

= Models never load, or visitors see "Vehicle models could not be loaded" =

Usually a page cache is serving an expired token (see **Page caching**; visitors are told to reload) or TowX was briefly unreachable. After TowX does not answer, the plugin waits about 90 seconds before asking again; if TowX rejects one make, that make is retried after 5 minutes. Turn on **Browser console** under **Forms → Settings → TOPS** to see details in the browser's developer console (F12).

= What do the request log statuses mean? =

* **Success** – TowX returned a Call ID.
* **API error** – TowX rejected the request. Check the credentials, the environment, and the error shown on the row.
* **HTTP error** – TowX answered with an error status (for example 500).
* **Transport error** – the site could not reach TowX (DNS, TLS or timeout). Contact your host if it continues.
* **No Call ID** – TowX answered without a Call ID. Check in TOPS whether the call was created **before** resending, because a resend creates a new call.

= The confirmation does not show the Call ID =

The result is only added to **Text** (message) confirmations, not redirects, and only when the integration is enabled for that form.

= I do not receive error emails =

Check **Error notification email** under **Forms → Settings → TOPS** and that your site can send email. With **Logging** turned on, the PHP error log records lines starting with `[GF TOPS]`, including when an email was skipped.

= Where are my TowX password and key stored? =

In Gravity Forms form meta for that form, encrypted with Gravity Forms' built-in encryption (AES-256-CTR + HMAC). User ID and Session ID are stored as entered. Anyone with both database access and `wp-config.php` could still decrypt the encrypted values, so restrict who can edit forms and use HTTPS for wp-admin.

= Who can see the request log? =

Users who can view Gravity Forms entries. Resending requires permission to edit entries. Log rows contain customer details from the request, with the TowX User ID, password, Session ID and authentication key removed.

= What should I send when asking for support? =

Plugin, WordPress, PHP and Gravity Forms versions; the API environment; the form ID, entry ID and request-log row; the status and error text; and the **Test authentication** result. **Never send** your TowX password, Session ID or authentication key.

== External services ==

This plugin connects to:

* **TowXchange (TowX / TOPS)** – `api.towxchange.net` (Production) or `apiqa.towxchange.net` (QA), or the custom address you enter. Each submitted request (name, phone, locations, vehicle, tag, notes) and your TowX credentials are sent to create the call, and vehicle lists are requested to fill the drop-downs. See TowXchange's terms and privacy policy.
* **GitHub** – `api.github.com` and `github.com` are checked for plugin updates and to download them. No visitor data is sent.

== Privacy ==

Request-log rows (including customer details from each request) are stored in your site's database until they are erased with **Tools → Erase Personal Data** or the plugin is deleted. Rows are matched to a person by their email address (in the Gravity Forms entry or the stored request); requests submitted without that email address must be found in **Forms → TOPS log** by name or phone. Failure notification emails include the request details and stay in the recipient's mailbox. Suggested privacy-policy text is added under **Settings → Privacy → Policy Guide**.

== Changelog ==

= 1.5.0 =
* **Visitors:** The Model drop-down shows "Loading models…" and a clear message if models cannot be loaded or none exist, instead of going blank. The message is announced to screen readers.
* **Admins:** New **Setup checklist** on each form's TOPS settings (credentials, connection test, field mapping, contact line, environment, and the last failed TowX lookup).
* **Admins:** **Settings** link on the Plugins screen; clearer field-map labels; the request log shows what actually happened when you resend.
* **Privacy:** Suggested privacy-policy text, and request-log rows are included in **Tools → Export / Erase Personal Data**.
* **Security:** The request log now requires permission to view Gravity Forms entries (resending requires permission to edit entries). A custom API address is always called over HTTPS, with a warning if it is not a TowX address. The API address is no longer printed in public page source.
* **Reliability:** When TowX is unreachable, vehicle lookups pause for about 90 seconds instead of slowing every page view.
* **Accessibility:** The ⚡ marker on TowX-filled fields has proper screen-reader text.
* **Translations:** Translation template (`languages/gravity-forms-tops.pot`) included.
* **Releases:** Signed build provenance and stricter release checks.

= 1.4.1 =
* **Updates:** Update checks now also run in the background, so new versions appear (and auto-updates work) without someone visiting wp-admin.
* **Updates:** The **Enable auto-updates** toggle works for this plugin.
* **Updates:** Only official release packages of this plugin are installed, and the update details show each release's own WordPress / PHP requirements.
* **Updates:** Sites that installed the plugin under a different folder name stay active after updating.
* Fewer requests to GitHub, and **Dashboard → Updates → Check again** re-checks right away.

= 1.4.0 =
* **Security:** TowX failure emails and the short-lived confirmation transient now contain the *redacted* Create Call XML. Earlier versions emailed the TowX password, Session ID and authentication key in plain text.
* **Security:** Stored secrets are no longer decrypted into the form settings screen; they are decrypted only when calling TowX. "Test authentication" still uses the saved key when the field is left blank.
* **Security:** Model lookups (AJAX and on submit) only accept make keys from TowX's GetMakes list, and submit-time lookups run only for a real submission of that form, so arbitrary requests cannot spend the site's TowX quota.
* **Security:** On upgrade, any remaining plaintext TowX password / authentication key in form settings is encrypted automatically, including inactive and trashed forms (no need to re-save each form).
* **Fix:** VehicleInfo now sends the model *name* (not its TowX key) by loading models for the submitted make on the server. Re-rendering after a validation error keeps the visitor's model choice.
* **Fix:** Resend from the request log now uses make / model / color names in VehicleInfo (previously sent raw keys).
* **Fix:** Blank or partial addresses no longer produce values like ", ,  " in Location / Destination.
* **Fix:** Control characters pasted into form fields are stripped so they cannot make the Create Call XML invalid.
* **Fix:** The request-log table is created on first write, so submissions right after an upgrade are logged even before an admin opens wp-admin.
* **Performance:** GetMakes, GetColors and GetModelsForMake results are cached for one hour (filter `gf_tops_vehicle_list_cache_ttl`, 0 disables). Previously every render, validation and submission called TowX.
* **Admin:** Resending a request-log row that already returned a Call ID asks for confirmation (a resend creates a second tow call).
* **Privacy:** The `<!-- GF TOPS … -->` footer comment is shown only when browser console logging or WP_DEBUG is on (filter `gf_tops_show_footer_diagnostic_comment`).
* **New filter:** `gf_tops_models_require_nonce` for sites that cannot exclude tow-form pages from long-lived page caches.
* **Uninstall:** Cleans up every site on multisite and removes cached plugin transients.
* **Updates:** GitHub updater, plugin URI and security contact now point to `duppinstech/gravity-forms-tops`.
* **Development:** Composer dev tooling, PHPCS ruleset, PHPUnit tests, CI on PHP 7.4–8.3, and release checks that the tag matches the plugin version.

= 1.3.9 =
* **GitHub updates:** Removed the `upgrader_source_selection` hook that returned plugin paths without a trailing slash (WordPress core already picks the correct folder for single-root ZIPs). That mismatch caused “The package could not be installed” on many hosts.
* **GitHub updates:** Set `Accept: application/octet-stream`, a consistent User-Agent, and a longer HTTP timeout when downloading `.zip` assets from GitHub / objects.githubusercontent.com.

= 1.3.8 =
* Confirmation **{organization}** and default “Thank you for contacting …” now use the **WordPress Site Title** (Settings → General → Site Title, option `blogname`) when the per-form override field is left blank.

= 1.3.7 =
* **Per-form TowX confirmation text** (Form Settings → TOPS → TowX confirmation text): optional override for the name shown in defaults and `{organization}` (otherwise uses **Settings → General → Site Title**), optional contact HTML when TowX fails (overrides global setting per form), and optional custom success/error/unclear templates with placeholders `{call_id}`, `{error}`, `{organization}`, `{site_name}`, `{contact_html}`.
* Default success intro thanks the visitor using the **site title** when no override is set; replaced generic “our team” follow-up with neutral copy.

= 1.3.6 =
* **Confirmation timing:** Run TowX Create Call on `gform_pre_handle_confirmation` (before the confirmation is built) instead of `gform_after_submission`, so the confirmation message can reflect success (Call ID), API error, or an ambiguous state. Previously the result transient was stored too late for `gform_confirmation` to see it.
* Richer default confirmation HTML for those outcomes; optional site-wide **Confirmation: contact line when TowX fails** (HTML) under Forms → Settings → TOPS (shown on errors and ambiguous states; message confirmations only, not redirects).
* Error notification email: trim + `sanitize_email`; optional diagnostic line in the PHP error log when logging is enabled and the address is missing/invalid.
* PHPCS: document direct `$wpdb` use for the custom request-log table and uninstall `DROP TABLE`.

= 1.3.5 =
* Encrypt TowX **password** and **authentication key** at rest in form meta using `GFCommon::openssl_encrypt` (same scheme as Gravity Forms). Existing plaintext values migrate to ciphertext the next time form settings are saved.

= 1.3.4 =
* Production readiness: Plugin Check fixes — translators comments for JS i18n strings with placeholders; escaped admin request-log output; documented nonce waiver for read-only GET navigation; `phpcs:ignore` on required `http_api_curl` options (no `wp_remote_*` equivalent for these TLS/SNI flags).
* Database: use `$wpdb->prepare()` identifier placeholder `%i` for the custom log table (requires **WordPress 6.2+**); `DROP TABLE` on uninstall uses the same pattern.
* `load_plugin_textdomain` PHPCS note for non–WordPress.org distribution.
* `languages/index.php` instead of a hidden placeholder file; readme `Tested up to` / tag count for WordPress.org rules.
* **Minimum WordPress** version raised to **6.2** (identifier placeholders).

= 1.3.3 =
* Register Elementor HTML capture on `elementor/loaded` (not only `wp`) so `gform_wrapper_*` IDs are recorded before `wp_footer` enqueue.
* Footer HTML comment `<!-- GF TOPS … -->` lists discovered form IDs, integration on/off, and whether `gf-tops-frontend` enqueued (View Source → search “GF TOPS”).

= 1.3.2 =
* Discover Gravity Forms IDs from rendered HTML (`gform_wrapper_{id}`) via late `the_content`, widgets, `render_block`, and Elementor `elementor/frontend/the_content` — fixes missing scripts when page builders never expose shortcodes in raw post_content.

= 1.3.1 =
* Discover Gravity Forms on singular pages using `get_queried_object_id()` (fixes empty discovery when `global $post` is unset during `wp_enqueue_scripts`).
* Scan Elementor `_elementor_data` JSON for form IDs / embedded shortcodes.
* `wp_footer` fallback enqueue if TOPS assets were not queued earlier.

= 1.3.0 =
* Front-end status callout when TOPS is enabled (API environment, makes/colors counts, model cascade status); DOM recount after Gravity Forms renders.
* Enqueue TOPS scripts via `wp_enqueue_scripts` when form IDs are found in post shortcodes/blocks (fallback when `gform_enqueue_scripts` does not run).
* Filter `gf_tops_discover_form_ids` for page builders that embed forms outside post_content.
* Cascade diagnostics optional UI; hidden-field sync options; improved GetModelsForMake error handling.

= 1.2.0 =
* Request log: each Create Call attempt is stored (redacted request XML, raw response, HTTP code, Call ID when TowX returns CallKey).
* Forms → TOPS settings: link to the log; Forms → TOPS log submenu lists attempts per form with pagination.
* Resend Create Call from the log (rebuilds XML from the current entry and saved credentials; does not re-trigger confirmation email notifications).

= 1.1.0 =
* Mask password and authentication key in UI; preserve on save when left blank.
* Create Call XML uses the same API environment as JSON (production / QA / custom); removed per-form Create Call URL.
* VehicleInfo XML value built from Year, Make, Model, and Color mappings (labels when available).
* Admin authentication test (GetMakes) with detailed diagnostics on failure or unexpected responses.
* Repository layout: `includes/`, `assets/`, `languages/`, legal and security docs.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.5.0 =
The request log now requires the Gravity Forms "view entries" permission (and "edit entries" to resend). Users who could only edit forms lose access, and roles that can view entries but not edit forms (for example support staff) gain access to the customer details in the log. Check custom roles after updating.

= 1.4.0 =
Security update: stops TowX credentials from being included in failure notification emails. If error emails were enabled on an earlier version, rotate the TowX password and authentication key.

= 1.3.4 =
Requires **WordPress 6.2 or newer** (database identifier placeholders). Stay on 1.3.3 if you must run WordPress 6.0–6.1.

= 1.1.0 =
Secrets UI, environment-based TowX URLs, VehicleInfo from year/make/model/color, and richer connection-test diagnostics.
