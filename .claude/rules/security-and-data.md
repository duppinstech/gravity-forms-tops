# Security and Data Rules

- Treat TowX credentials, session identifiers, form submissions, and API responses as sensitive.
- Follow [SECURITY.md](../../SECURITY.md) for the current storage model and disclosure process; do not describe unencrypted values as encrypted.
- Never include credentials or real customer data in logs, error messages, tests, examples, commits, or screenshots.
- For AJAX or admin actions, verify nonce and capability checks and validate all input before use.
- Escape output for its destination. Use prepared database operations and preserve existing query safety.
- Do not weaken TLS verification or add endpoint exceptions without a documented, narrowly scoped requirement.
- Review uninstall and migration changes for data loss, upgrade safety, and multisite implications.
