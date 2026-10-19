# Project Context

- WordPress plugin connecting Gravity Forms to TowX / TOPS (TOPSLink).
- Minimum supported runtime: PHP 7.4 and WordPress 6.2; Gravity Forms must be active.
- Main integration code lives in `includes/`; front-end and admin scripts live in `assets/`.
- The release workflow creates a WordPress-ready ZIP when a `v*` tag is pushed.
- Dev-only Composer tooling: `composer lint` / `phpcs` / `test`. Unit tests run without WordPress via stubs in `tests/bootstrap.php`; CI runs them on PHP 7.4–8.4.
- GitHub repository: `duppinstech/gravity-forms-tops` (formerly `oduppinsjr/…`, which redirects).
- Ownership: see `OWNERSHIP.md` (Duppins Technology).
- Check `SECURITY.md` before making claims about credential storage or sensitive-data handling.
