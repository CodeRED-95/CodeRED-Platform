#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONFIGURATION="${CONFIGURATION:-Release}"

dotnet publish "$ROOT/CodeRED.Plugin.Anime.sln" \
  --configuration "$CONFIGURATION" \
  /property:GenerateFullPaths=true \
  /consoleloggerparameters:NoSummary
