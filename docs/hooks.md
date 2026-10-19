# Hooks reference

Filters provided by GF TowX Integration. The plugin defines one action, `gf_tops_limit_page_cache_ttl` (`$ttl, $form_id`), fired on pages with the model drop-down so you can cap other page caches' lifetime. Add filters from a site-specific plugin or the theme's `functions.php`, and test changes on a staging site with QA TowX credentials first.

## Confirmation

| Filter | Arguments | Default | Use |
|---|---|---|---|
| `gf_tops_confirmation_fragment_html` | `$html, $form, $entry, $data` | Built-in success / error / unclear block | Change the TowX result block added to the confirmation. `$data` has `call_key`, `error` and `raw`. Escape anything you add. |
| `gf_tops_replace_confirmation_with_status` | `$replace, $form, $entry, $data` | `false` | Return `true` to replace the whole confirmation message with the TowX block instead of appending it. |
| `gf_tops_confirmation_full_html` | `$html, $form, $entry, $data, $confirmation` | The TowX block | Full HTML used when replacing (see above). |

## Request log

| Filter | Arguments | Default | Use |
|---|---|---|---|
| `gf_tops_request_log_retention_days` | `$days` | **Keep request log for** setting (90) | Days to keep log rows before the daily WP-Cron cleanup (`gf_tops_purge_request_log`) deletes them. `0` keeps them forever. |

## Vehicle lists and model lookups

| Filter | Arguments | Default | Use |
|---|---|---|---|
| `gf_tops_vehicle_list_cache_ttl` | `$ttl, $verb` | `HOUR_IN_SECONDS` | Seconds to cache GetMakes / GetColors / GetModelsForMake results. `0` disables caching. |
| `gf_tops_lookup_failure_backoff` | `$seconds, $verb` | `90` | Seconds to pause lookups for a set of credentials after an outage (no response, 429 or 5xx). Other TowX errors only cool down that make for 5 minutes. `0` disables the pause (failures are still shown on the settings checklist). |
| `gf_tops_page_cache_max_ttl` | `$ttl, $form_id` | `36000` (10 hours) | Longest page-cache lifetime for pages with the model drop-down (sent to LiteSpeed Cache automatically). `0` leaves caching alone. |
| `gf_tops_models_require_nonce` | `$require, $form_id` | `true` | Return `false` only if tow-form pages cannot be excluded from long-lived page caches. Make keys are still checked against TowX's make list. |

## Front-end discovery and diagnostics

| Filter | Arguments | Default | Use |
|---|---|---|---|
| `gf_tops_discover_form_ids` | `$ids, $post_id` | IDs found in content, blocks and Elementor data | Add form IDs for forms embedded somewhere the plugin cannot see (for example other page builders), so its scripts load. |
| `gf_tops_show_footer_diagnostic_comment` | `$show` | On when browser console logging or `WP_DEBUG` is on | Print the `<!-- GF TOPS … -->` footer comment. |

## HTTP / TLS (TowX hosts only)

| Filter | Arguments | Default | Use |
|---|---|---|---|
| `gf_tops_http_force_ipv4` | `$force` | `true` | Force IPv4 for `*.towxchange.net` (works around broken IPv6 / SNI paths). |
| `gf_tops_http_force_http11` | `$use_11` | `true` | Force HTTP/1.1. |
| `gf_tops_http_force_tls12` | `$set_tls12` | `true` | Require TLS 1.2 or newer. |

## API product

| Filter | Arguments | Default | Use |
|---|---|---|---|
| `gf_tops_api_product` | `$product` | **API product** setting, or `TOPSLink` when that is the default or the custom value is blank or invalid | Product sent on Create Call and on vehicle lookups. Return only letters, numbers, spaces, hyphens or underscores; anything else is sent as `TOPSLink`. |

## Credentials

| Filter | Arguments | Default | Use |
|---|---|---|---|
| `gf_tops_secret_field_names` | `$names` | `tops_password`, `tops_auth_key` | Setting keys encrypted at rest. |
| `gf_tops_encrypt_secrets_at_rest` | `$enabled` | `true` when Gravity Forms encryption is available | Turning this off stores new secrets in plain text. Not recommended. |

## Updates

| Filter | Arguments | Default | Use |
|---|---|---|---|
| `gf_tops_github_updater_config` | `$config` | owner `duppinstech`, repo `gravity-forms-tops`, 6 h / 1 h cache | Change updater settings. See [github-updater-standard.md](github-updater-standard.md). |
| `gf_tops_github_release_http_args` | `$args, $url, $config` | 10 s timeout, GitHub JSON headers | Adjust the GitHub API request. |

Constant `GF_TOPS_DISABLE_GITHUB_UPDATER` (set to `true` in `wp-config.php`) turns the updater off.
