---
description: Check release readiness and ZIP packaging without publishing a release.
---

Review the current changes and release workflow without creating a tag or publishing artifacts.

1. Confirm the working tree and target commit are understood.
2. Check version consistency in the plugin header, constants, and `readme.txt` where applicable.
3. Inspect `.github/workflows/release.yml` and verify the package includes runtime plugin files while excluding development-only files.
4. Run only validation commands available in the environment; do not invent credentials or contact production TowX endpoints.
5. Report blockers and checks not performed. Never create or push a release tag unless explicitly asked.
