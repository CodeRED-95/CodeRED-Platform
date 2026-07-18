$ErrorActionPreference = 'Stop'

Set-Location $PSScriptRoot

docker compose up -d app postgres redis
if ($LASTEXITCODE -ne 0) {
    exit $LASTEXITCODE
}

docker compose exec -T app composer check
exit $LASTEXITCODE
