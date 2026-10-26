#!/usr/bin/env bash
#
# Auto Updates Status for MainWP
# Copyright (c) 2026 ProAdmin <https://proadmin.com>
# Licensed under the GPL-2.0-or-later.
#
# Builds dist/auto-updates-status-for-mainwp.zip from the committed files,
# including the user guide, installation guide and licence.

set -euo pipefail

cd "$(dirname "$0")/.."

SLUG="auto-updates-status-for-mainwp"
OUT="dist/${SLUG}.zip"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

mkdir -p dist "$TMP/$SLUG"

# Plugin code, licence and guides; repository-only files stay out.
git archive HEAD \
	auto-updates-status-for-mainwp.php uninstall.php index.php readme.txt LICENSE.txt \
	includes assets languages \
	docs/user-guide.pdf docs/installation-guide.pdf \
	| tar -x -C "$TMP/$SLUG"

rm -f "$OUT"
( cd "$TMP" && zip -qrX "$OLDPWD/$OUT" "$SLUG" )

echo "Built $OUT"
unzip -l "$OUT" | tail -n 1
