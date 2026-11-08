#!/usr/bin/env bash
set -euo pipefail

project_root="$(cd "$(dirname "$0")/.." && pwd)"
revision="${1:-HEAD}"
snapshot_root="$(mktemp -d "${TMPDIR:-/tmp}/tailwind-merge-baseline.XXXXXX")"

git -C "$project_root" archive "$revision" | tar -x -C "$snapshot_root"
mkdir -p "$snapshot_root/vendor/psr"
cp -R "$project_root/vendor/psr/simple-cache" "$snapshot_root/vendor/psr/"

printf '%s\n' "$snapshot_root"
