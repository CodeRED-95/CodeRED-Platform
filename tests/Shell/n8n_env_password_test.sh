#!/usr/bin/env bash
set -Eeuo pipefail

normalize_env_secret() {
    local value="$1"
    if [[ ${#value} -ge 2 ]]; then
        if [[ "$value" == \"*\" && "$value" == *\" ]]; then
            value="${value:1:${#value}-2}"
        elif [[ "$value" == \'*\' && "$value" == *\' ]]; then
            value="${value:1:${#value}-2}"
        fi
    fi
    printf '%s' "$value"
}

set_env_value_raw() {
    local file="$1" key="$2" value="$3" tmp
    [[ "$value" != *$'\n'* && "$value" != *$'\r'* ]]
    touch "$file"
    tmp="$(mktemp)"
    awk -v k="$key" -v v="$value" '
        BEGIN{done=0}
        index($0,k"=")==1 {if(!done){print k"="v; done=1}; next}
        {print}
        END{if(!done) print k"="v}
    ' "$file" > "$tmp"
    mv "$tmp" "$file"
}

assert_equal() {
    local expected="$1" actual="$2" label="$3"
    if [[ "$expected" != "$actual" ]]; then
        echo "[FAIL] $label" >&2
        exit 1
    fi
}

file="$(mktemp)"
trap 'rm -f "$file"' EXIT

cases=(
    'Simple123|Simple123'
    '"MiClave123"|MiClave123'
    "'MiClave123'|MiClave123"
    'Clave#123|Clave#123'
    'Clave$123|Clave$123'
    'Clave=123|Clave=123'
    'Clave con espacio|Clave con espacio'
    "Clave'123|Clave'123"
    'Clave"123|Clave"123'
)

for entry in "${cases[@]}"; do
    input="${entry%%|*}"
    expected="${entry#*|}"
    normalized="$(normalize_env_secret "$input")"
    assert_equal "$expected" "$normalized" "normalize"
    set_env_value_raw "$file" DB_POSTGRESDB_PASSWORD "$normalized"
    line="$(grep '^DB_POSTGRESDB_PASSWORD=' "$file")"
    assert_equal "DB_POSTGRESDB_PASSWORD=$expected" "$line" "write raw"
    count="$(grep -c '^DB_POSTGRESDB_PASSWORD=' "$file")"
    assert_equal "1" "$count" "dedupe"
done

if normalize_env_secret '"a"b"' | grep -qx 'a"b'; then
    :
else
    echo '[FAIL] preserves internal quotes' >&2
    exit 1
fi

echo '[OK] n8n env password serialization tests passed.'
