# Duppins Technology GitHub updater standard (v1.1.0)

Every Duppins Technology WordPress plugin distributed through GitHub Releases uses the same updater code and the same release pipeline. This document is the contract. The reference implementation lives in the `duppinstech/gravity-forms-tops` repository:

| Part | Reference file |
|------|----------------|
| Updater class | `includes/class-gf-tops-github-updater.php` |
| Boot call and `Update URI` header | `gravity-forms-tops.php` |
| Unit tests | `tests/GitHubUpdaterTest.php`, fixture `tests/fixtures/sample-plugin/`, doubles in `tests/bootstrap.php` (section "Doubles used by the GitHub updater tests") |
| Release workflow | `.github/workflows/release.yml` |
| Release notes | `bin/release-notes.sh` |
| ZIP build | `bin/build-zip.sh` |

`STANDARD_VERSION` in the class says which version of this document a copy implements. Compare it across repos to spot drift.

## Porting rules

1. Copy the class file **verbatim**. Change only the class name, the file name, and the `@package` tag to the plugin's own prefix (for example `LPM_GitHub_Updater` in `includes/class-lpm-github-updater.php`, or a namespaced class if the plugin uses namespaces). Each plugin needs its own copy: two plugins on one site must never share one class name.
2. Do not change behavior in a copy. To change behavior, change the reference, bump `STANDARD_VERSION`, and re-port.
3. Boot it from the main plugin file at load time, not on `admin_init`, `is_admin()`, or a settings check:

   ```php
   require_once __DIR__ . '/includes/class-xyz-github-updater.php';
   XYZ_GitHub_Updater::boot(
   	array(
   		'file'    => __FILE__,          // main plugin file
   		'version' => XYZ_VERSION,       // installed version constant
   		'owner'   => 'duppinstech',
   		'repo'    => 'xyz',
   		'prefix'  => 'xyz',             // option + filter prefix
   	)
   );
   ```
4. Add `Update URI: https://github.com/<owner>/<repo>` to the main plugin header. WordPress 5.8+ then does not take updates for this plugin from WordPress.org.
5. Remove the plugin's previous updater completely: its class, hooks, its own `upgrader_source_selection` folder renames (the standard has one), "update source" settings, and manual "check for updates" links. Core's **Dashboard → Updates → Check again** resets this updater's cache.
6. In `uninstall.php`, delete the `<prefix>_github_release` site option and any cache keys the old updater used.
7. Copy `bin/release-notes.sh` verbatim and call it from the release workflow ("Write release notes" step, `body_path: release-notes.md`) and from CI ("Release notes for the current version" step), as this repository does. Keep a non-empty changelog section for the current version: `= x.y.z =` under `== Changelog ==` in `readme.txt`, or `## [x.y.z] - date` in `CHANGELOG.md`.

## Required behavior

| ID | Behavior |
|----|----------|
| U1 | Hooks are registered when the plugin loads, so WP-Cron update checks and background auto-updates see releases. |
| U2 | Checks GitHub only from `pre_set_site_transient_update_plugins` and the plugin's own "View details" request. It never calls GitHub on page loads or transient reads. |
| U3 | One network-wide site option `<prefix>_github_release` stores the parsed release, its ETag, the last and next check times, and whether a rate-limit wait is active. |
| U4 | After success, the next check is 6 hours later. Then it sends `If-None-Match`; a `304` reuses the cached release and does not count against GitHub's rate limit. |
| U5 | Network errors and other failures wait 1 hour. `403`/`429` wait until `Retry-After` / `X-RateLimit-Reset` (at most 1 day). The last good release keeps being served while waiting. |
| U6 | A `404`, or a latest release with no usable asset, clears the cached release so no broken package is offered. |
| U7 | Installs only a release asset: the one named `<repo>.zip`, or the only `.zip` asset. Never a source zipball. |
| U8 | Every package URL, from the release or already in the update list, must be a `.zip` under `https://github.com/<owner>/<repo>/releases/download/<tag>/`. Other repositories, `archive/` and `codeload` URLs are rejected. |
| U9 | Tags are `v`-prefixed or bare numeric versions (`1.2.3`); drafts and prereleases are ignored. Comparison ignores trailing `.0` parts, so `1.4.1.0` never loops as an update over `1.4.1`. |
| U10 | Every check puts the plugin in `response` (newer) or `no_update` (current), with `id`, `slug`, `plugin`, `new_version`, `url`, `package`, `requires`, `requires_php`, `tested`. This replaces any same-slug entry from WordPress.org; when no release is available, any entry whose package fails U8 is removed. |
| U11 | "View details" is answered for this slug from the cache (never WordPress.org). Release notes are escaped, links made clickable, then passed through `wp_kses_post`. |
| U12 | Cache resets come from Dashboard → Updates → **Check again** (`load-update-core.php` with `force-check`, users with `update_plugins` only) and from `delete_site_transient_update_plugins` (after plugin upgrades). A reset keeps the ETag, never shortens a rate-limit wait, and keeps checks at least 60 seconds apart. |
| U13 | `http_request_args` changes only this repository's `/releases/download/` URLs (binary `Accept`, 60 s timeout). |
| U14 | An optional token (config or `<prefix>_github_updater_config` filter) is sent only to `api.github.com`, with redirects turned off. Never ship a token in a plugin. Private repositories are not supported. |
| U15 | `define( '<PREFIX>_DISABLE_GITHUB_UPDATER', true )` turns it off. |
| U16 | `upgrader_source_selection` renames the unpacked folder to the *installed* folder name when they differ (for example a site that installed `repo-main/`), so the plugin stays active after updating. Only for this plugin's own upgrade. |
| U17 | `requires`, `requires_php` and `tested` come from the release notes lines `Requires at least:`, `Requires PHP:`, `Tested up to:` (written by the release workflow), falling back to the installed copy's header and `readme.txt`. |

Filters: `<prefix>_github_updater_config`, `<prefix>_github_release_http_args`.

## Release pipeline

| ID | Requirement |
|----|-------------|
| R1 | Pushing a `v*` tag runs `.github/workflows/release.yml`. |
| R2 | The workflow fails unless the tag equals the plugin header `Version`, the version constant and `readme.txt` `Stable tag`. |
| R3 | The workflow runs lint and tests before packaging. |
| R4 | `bin/build-zip.sh` builds `<slug>.zip` from tracked files only, with a single top-level `<slug>/` folder, and excludes development files (`.github/`, `.claude/`, `tests/`, `bin/`, `docs/`, agent docs, Composer and PHPCS/PHPUnit config). |
| R5 | The ZIP is attached to the GitHub release as an asset named `<repo>.zip`. |
| R6 | The release notes start with a `## Requirements` block listing `Requires at least`, `Requires PHP` and `Tested up to` from the plugin header and `readme.txt` (written by `bin/release-notes.sh`, which fails if any of the three is missing). |
| R7 | After the requirements, the release notes contain the version's changelog section: `CHANGELOG.md` `## [x.y.z]` when that file exists, otherwise `readme.txt` `= x.y.z =` under `== Changelog ==`. The workflow fails if the section is missing, empty, or marked Unreleased; CI runs the same script for the current version on every pull request, so a version bump without its changelog section fails before tagging. (Added in v1.1.0.) |

## Verification for a port

- The reference tests pass against the copy (rename the class in the test, point the fixture at a sample plugin).
- `bash bin/release-notes.sh <version> <main file> /tmp/notes.md` prints the Requirements block followed by `## Changes in <version>`; after the first release, the GitHub release body shows the same.
- `grep` finds no old updater hooks, settings or classes.
- The main plugin header has `Update URI`.
- The repository is **public** (or the plugin uses a separate public release channel); a private repository returns 404 to every site.
- After the first release built with the new pipeline, unzip the asset and confirm only runtime files and one top-level folder.
