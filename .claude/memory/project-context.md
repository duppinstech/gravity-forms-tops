# Project Context

- WordPress plugin connecting Gravity Forms to TowX / TOPS (TOPSLink).
- Minimum supported runtime: PHP 7.4 and WordPress 6.2; Gravity Forms must be active.
- Main integration code lives in `includes/`; front-end and admin scripts live in `assets/`.
- The release workflow creates a WordPress-ready ZIP when a `v*` tag is pushed.
- No Composer, PHPUnit, npm, or dedicated automated test suite is currently committed.
- Check `SECURITY.md` before making claims about credential storage or sensitive-data handling.
