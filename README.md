# Gravity Forms TOPS / TowX Integration

WordPress plugin by **Duppins Technology** that connects [Gravity Forms](https://www.gravityforms.com/) to the **TowX / TOPS (TOPSLink)** API for tow dispatch (Create Call XML), with per-form field mapping and dynamic vehicle dropdowns.

Ownership and licensing to clients are described in [OWNERSHIP.md](OWNERSHIP.md).

## Repository layout

| Path | Purpose |
|------|---------|
| `gravity-forms-tops.php` | Bootstrap, constants, TLS helper registration, add-on registration |
| `uninstall.php` | Removes plugin options, cached transients and the request-log table (every site on multisite); keeps per-form settings |
| `includes/class-gf-tops-addon.php` | Gravity Forms add-on: settings, field map, submission, AJAX, request-log screen |
| `includes/class-gf-tops-xml.php` | Create Call XML build, redaction, POST, response parsing |
| `includes/class-gf-tops-entry.php` | Entry values, addresses, names, VehicleInfo |
| `includes/class-gf-tops-secrets.php` | Encryption of TowX password / authentication key at rest |
| `includes/class-gf-tops-request-log.php` | Request-log database table |
| `includes/class-gf-tops-http.php` | TLS / cURL adjustments for TowX hosts |
| `includes/class-gf-tops-github-updater.php` | Update checks against GitHub Releases |
| `assets/` | Front-end model cascade and admin connection-test scripts and styles |
| `languages/` | Translation files |
| `tests/`, `bin/`, `composer.json`, `phpcs.xml.dist`, `phpunit.xml.dist` | Development tooling (not shipped in the release ZIP) |

## Requirements

- WordPress **6.2+**
- PHP **7.4+**
- **Gravity Forms 2.5+** (active, licensed)

## Install

1. Install the `gravity-forms-tops.zip` from the latest [GitHub release](https://github.com/duppinstech/gravity-forms-tops/releases) (or copy this repository into `wp-content/plugins/gravity-forms-tops/`).
2. Activate **Gravity Forms TOPS / TowX Integration** in **Plugins**.
3. Configure **Forms → Settings → TOPS** (global: **API environment** for both JSON and Create Call XML, optional error email, logging).
4. For each form: **Form → Settings → TOPS** — enable the integration, set credentials, map fields.

Later releases are offered through the normal WordPress **Plugins → Updates** screen (GitHub Releases updater). Define `GF_TOPS_DISABLE_GITHUB_UPDATER` as `true` to turn this off.

## Features

- **Field map** for Create Call data (plus optional lines prepended to dispatch notes). **VehicleInfo** is built from the **Year, Make, Model, and Color** choice labels.
- **Live vehicle lists:** Make and Color selects are filled from TowX GetMakes / GetColors; Model is loaded with GetModelsForMake when a make is chosen. Lists are cached for one hour (filter `gf_tops_vehicle_list_cache_ttl`).
- **Credentials:** password and authentication key are encrypted at rest and never re-displayed; leave blank to keep the stored value.
- **Test authentication** runs GetMakes and shows a summary plus detailed response on failure.
- **Request log** (Forms → TOPS log) with redacted request XML, TowX responses, and **Resend Create Call**.
- **TLS helpers** for `towxchange.net` hosts (filters `gf_tops_http_*`).

## Production notes

- **Page caching:** the model dropdown uses an AJAX nonce. If a full-page cache serves tow-form pages for longer than 12 hours, exclude those pages from the cache (preferred), or return `false` from `gf_tops_models_require_nonce`.
- **Diagnostics:** the `<!-- GF TOPS … -->` footer comment appears only when browser console logging or `WP_DEBUG` is on (filter `gf_tops_show_footer_diagnostic_comment`).
- **Request log retention:** log rows include customer details from the submission and are kept indefinitely (until the plugin is uninstalled) by design.

## Development

Composer is used for development tooling only; the plugin has no runtime dependencies.

```bash
composer install     # dev tools
composer lint        # php -l on every plugin PHP file
composer phpcs       # WordPress security / i18n / PHP 7.4+ compatibility sniffs
composer test        # PHPUnit unit tests (no WordPress or credentials needed)
composer check       # all of the above
bash bin/build-zip.sh  # build gravity-forms-tops.zip locally (needs zip)
```

CI (`.github/workflows/ci.yml`) runs these on PHP 7.4–8.3. Pushing a `v*` tag runs `.github/workflows/release.yml`, which checks the tag against the plugin version, re-runs lint and tests, and attaches the ZIP to the GitHub release.

## Security

See [SECURITY.md](SECURITY.md). TowX password and authentication key are encrypted at rest in form meta; User ID and Session ID are not. Limit form-admin access and use HTTPS.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE). Copyright Odell Duppins / Duppins Technology.

## Legal

Gravity Forms is a trademark of Rocketgenius, Inc. This project is not affiliated with or endorsed by Rocketgenius or TowXchange. TowX / TOPS are used here to describe API compatibility only.
