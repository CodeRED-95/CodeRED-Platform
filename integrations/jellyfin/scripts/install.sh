#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONFIGURATION="${CONFIGURATION:-Release}"
PLUGIN_DIR="${JELLYFIN_PLUGIN_DIR:-/var/lib/jellyfin/plugins/CodeRED Anime}"
PUBLISH_DIR="$ROOT/CodeRED.Plugin.Anime/bin/$CONFIGURATION/net8.0/publish"

"$ROOT/scripts/build.sh"
mkdir -p "$PLUGIN_DIR"
cp "$PUBLISH_DIR"/CodeRED.Plugin.Anime.* "$PLUGIN_DIR"/

echo "Installed CodeRED Anime plugin into $PLUGIN_DIR"
