# Contributing

Thank you for helping improve this plugin. It is owned by Duppins Technology; see [OWNERSHIP.md](OWNERSHIP.md). Contributions are accepted under the plugin's GPL-2.0-or-later license.

## Security

Do **not** open a public issue for undisclosed vulnerabilities. Follow [SECURITY.md](SECURITY.md) and report privately.

## Workflow

1. Open an issue first for larger changes (new features, refactors) so direction can be agreed on.
2. Fork the repository and create a branch from `main` (or the default branch).
3. Keep pull requests **focused**—one concern per PR when practical.
4. Match existing **PHP** style ([WordPress PHP coding standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)).
5. Do not commit **secrets** (TowX credentials, `.env`, API keys in fixtures, etc.).

## Project layout (where to change what)

| Area | Location |
|------|----------|
| Add-on settings, submission, AJAX, field map | `includes/class-gf-tops-addon.php` |
| XML build / POST / response parse | `includes/class-gf-tops-xml.php` |
| Entry values, VehicleInfo string, addresses | `includes/class-gf-tops-entry.php` |
| TLS / cURL tweaks for TowX | `includes/class-gf-tops-http.php` |
| Credential encryption at rest | `includes/class-gf-tops-secrets.php` |
| Request-log table | `includes/class-gf-tops-request-log.php` |
| GitHub Releases updater | `includes/class-gf-tops-github-updater.php` |
| Release package contents | `bin/build-zip.sh` |
| Unit tests and WordPress stubs | `tests/` |
| Front-end model cascade | `assets/js/frontend.js` |
| Form settings (test auth UI) | `assets/js/admin-form-settings.js`, `assets/css/admin-form-settings.css` |

## Automated checks

```bash
composer install
composer check   # lint + phpcs + phpunit
```

CI runs the same checks on PHP 7.4–8.3 for every pull request. Unit tests use fake values only; never add real TowX credentials or customer data to `tests/`.

## Local testing

- WordPress **6.2+**, PHP **7.4+** (see plugin header).
- **Gravity Forms** must be installed and licensed in your environment.
- Install this plugin by symlinking or copying the folder into `wp-content/plugins/gravity-forms-tops/`.
- Use **Test authentication** on a form’s TOPS settings (with QA or production credentials) to verify GetMakes.

## Pull request checklist

- [ ] Change is scoped to the feature or fix described.
- [ ] `composer check` passes.
- [ ] No TowX or WordPress credentials in code or commits.
- [ ] New user-facing strings use the `gravity-forms-tops` text domain and are suitable for translation.
- [ ] If you touch HTTP/TLS behavior, note it in the PR for reviewers who test against real TowX endpoints.

## Translations

New user-facing strings should use the `gravity-forms-tops` text domain. Translation templates can live under `languages/` (e.g. a future `.pot` generated from the codebase).
