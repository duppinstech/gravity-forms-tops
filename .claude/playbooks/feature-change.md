# Feature Change Workflow

1. Confirm the requested behavior and inspect the owning code path.
2. Check `INTENT.md`, compatibility requirements, and relevant security rules.
3. Implement one focused behavior change and update affected documentation.
4. Run the narrowest available static or runtime validation; add tests when supported by project tooling.
5. Inspect the final diff for scope, secrets, compatibility, and translation/escaping concerns.
6. Run `/review-board` and fix its "must fix" items; bring owner decisions to the owner.
7. Summarize behavior changed, validation performed, review-board outcome, and remaining environment-dependent checks.
