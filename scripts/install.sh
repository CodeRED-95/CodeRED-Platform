#!/bin/sh
set -eu

if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        cp .env.example .env
    else
        echo "No existe .env ni .env.example."
        exit 1
    fi
fi

docker compose up -d --build
