# WordPress and PHP Rules

- Maintain compatibility with PHP 7.4 and WordPress 6.2 unless the task explicitly changes support policy.
- Follow WordPress PHP conventions and the patterns already used in adjacent code.
- Use WordPress sanitization, validation, capability checks, nonces, escaping, and translation APIs appropriate to each context.
- Keep PHP files protected against direct access where consistent with neighboring files.
- Preserve the `gravity-forms-tops` text domain. Escape translated output when rendering it.
- Keep changes localized to the owning class or asset; update documentation when behavior or setup changes.
- There is no committed Composer or PHPUnit configuration today. Check installed tools and state precisely which validation was run.
