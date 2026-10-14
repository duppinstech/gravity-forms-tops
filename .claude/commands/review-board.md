---
description: Run the department review board (security, legal, finance, product, QA, docs/support) on the current change and consolidate one report.
argument-hint: "[base ref or PR number; default: changes since main plus uncommitted work]"
---

Run the review board on the current change. This happens after development is finished and before anything is merged, tagged, or released.

1. Decide the scope: $ARGUMENTS if given (a base ref like `main`, or a PR number to read with `gh pr diff`); otherwise `git diff main...HEAD` plus uncommitted changes. Summarize the change in 3–5 lines (what changed, which files, user-visible effect).

2. Launch these subagents **in parallel, in one message**, giving each the same scope summary and the exact diff command to run:
   - `security-reviewer`
   - `legal-reviewer`
   - `finance-reviewer`
   - `product-reviewer`
   - `qa-reviewer`
   - `docs-support-reviewer`

   Tell each reviewer to stay read-only and to use its report format. A purely internal change (tests, CI, comments) may skip `finance-reviewer` and `legal-reviewer`; say so in the board report rather than skipping silently.

3. When all have reported, check any High or Medium finding that looks doubtful against the code yourself before repeating it. Drop findings you can show are wrong, and say why.

4. Present one consolidated report:

   ```
   # Review board: <change>
   | Department | Verdict | High | Medium | Low |
   |---|---|---|---|---|
   Must fix before merge: (High and Medium, deduplicated across departments, file:line, owner department)
   Should fix: (Low)
   Owner decisions: (numbered, each with options and a recommendation)
   Not verified: (combined gaps, e.g. needs a live WordPress site)
   ```

5. Do not fix anything as part of this command. Offer to fix the "Must fix" items, and wait for the owner on "Owner decisions".
