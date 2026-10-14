# Release Workflow

1. Confirm the release scope and ensure all intended changes are committed.
2. Bump the version in the plugin header, `GF_TOPS_VERSION`, and `readme.txt` Stable tag (the release workflow fails if they differ from the tag); add changelog and, if needed, upgrade-notice entries.
3. Run `composer check`; document checks that need WordPress, Gravity Forms or QA credentials and were not run.
4. Run `bash bin/build-zip.sh` (or inspect the staged `build/` folder) and confirm only runtime files ship; update the exclusion list there when adding dev-only paths.
5. Run `/review-board` on everything since the previous tag (`/review-board v<previous>`) and resolve its "must fix" items.
6. Push a `v*` tag only when explicitly authorized; `.github/workflows/release.yml` then builds and attaches the ZIP.
7. Verify the GitHub Actions run and published artifact before announcing the release.
