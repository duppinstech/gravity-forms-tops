# Bug Fix Workflow

1. Reproduce the issue or identify a nearby failing check before editing.
2. Trace the behavior to the code that directly computes or mutates it.
3. Fix the underlying cause with the smallest compatible change.
4. Run the reproduction or focused validation again.
5. Review related security, data migration, and API compatibility risks.
6. Run `/review-board` (at minimum `security-reviewer`, `qa-reviewer` and `docs-support-reviewer`; add the others when the fix changes user-visible behavior, data handling or licensing).
7. Report the root cause, fix, validation, review-board outcome, and any remaining reproduction limits.
