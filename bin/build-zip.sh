#!/usr/bin/env bash
# Build gravity-forms-tops.zip from tracked files only, excluding development files.
# Used by .github/workflows/release.yml; run locally with `bash bin/build-zip.sh` to inspect the package.
set -euo pipefail

cd "$(dirname "$0")/.."
rm -rf build gravity-forms-tops.zip
mkdir -p build/gravity-forms-tops

# Tracked files only (never local settings, vendor/, or untracked scratch files), minus dev-only paths.
git ls-files -z \
  | grep -zvE '^(\.github/|\.claude/|tests/|bin/|\.gitignore$|\.gitattributes$|AGENTS\.md$|CLAUDE\.md$|CONTRIBUTING\.md$|intent\.md$|plan\.md$|composer\.(json|lock)$|phpcs\.xml\.dist$|phpunit\.xml\.dist$)' \
  | xargs -0 -I{} cp --parents {} build/gravity-forms-tops/

( cd build && zip -qr9 ../gravity-forms-tops.zip gravity-forms-tops )
unzip -l gravity-forms-tops.zip
