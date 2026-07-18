#!/bin/sh
set -eu

MANIFEST="public/build/manifest.json"

if [ ! -f "$MANIFEST" ]; then
    echo "No existe $MANIFEST"
    exit 1
fi

CSS_FILE=$(php -r '$manifest = json_decode(file_get_contents("public/build/manifest.json"), true, 512, JSON_THROW_ON_ERROR); echo $manifest["resources/css/app.css"]["file"] ?? "";')
JS_FILE=$(php -r '$manifest = json_decode(file_get_contents("public/build/manifest.json"), true, 512, JSON_THROW_ON_ERROR); echo $manifest["resources/js/app.js"]["file"] ?? "";')

if [ -z "$CSS_FILE" ] || [ -z "$JS_FILE" ]; then
    echo "El manifest no contiene los assets esperados."
    exit 1
fi

curl -fsI "http://nginx/build/$CSS_FILE"
curl -fsI "http://nginx/build/$JS_FILE"
