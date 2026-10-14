# Release Workflow

1. Confirm the release scope and ensure all intended changes are committed.
2. Verify plugin version metadata and user-facing release notes.
3. Run available lint, test, and packaging checks; document unavailable checks.
4. Inspect the ZIP file list and ensure local settings, credentials, and development artifacts are excluded.
5. Push a `v*` tag only when explicitly authorized; `.github/workflows/release.yml` then builds and attaches the ZIP.
6. Verify the GitHub Actions run and published artifact before announcing the release.
