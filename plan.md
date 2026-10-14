# SDLC and Deployment Plan

This file tracks follow-up work for the plugin. Check current code and workflow state before starting an item; the list is not an instruction to broaden unrelated changes.

## Current deployment checks

- [ ] Validate installation and activation on WordPress 6.2+ with a supported PHP 7.4+ runtime and active Gravity Forms.
- [ ] In a non-production environment, configure a form and verify GetMakes authentication and a Create Call using authorized QA credentials.
- [ ] Before tagging a release, verify plugin version references, review the Git diff, and inspect the generated ZIP contents.

## SDLC follow-ups

- [ ] Choose a supported PHP lint / WordPress coding-standard setup and document reproducible local and CI commands.
- [ ] Add automated tests for credential encryption and migration, XML generation, and request/response handling without storing real credentials or customer data.
- [ ] Add a CI validation workflow after the supported checks and test environment are agreed.
- [ ] Review the release ZIP exclusion list when adding development-only files or new top-level directories.

## Completion notes

Record completed deployment checks with the release or pull request. Do not record secrets, live payloads, or personally identifiable customer data here.
