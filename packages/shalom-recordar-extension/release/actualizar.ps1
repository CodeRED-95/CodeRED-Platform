# Actualizador de "Registro de Actividad Shalom" para la instalacion sin
# empaquetar. Lo ejecuta una Tarea Programada. Compara la version instalada en
# la carpeta con la publicada en el servidor y, si hay una nueva, sobrescribe la
# carpeta. El navegador adopta la version nueva al reabrirse.
#
# pack-crx.mjs reemplaza CodeRED\shalom-recordar y https://platform.codered.lat/ext/shalom-recordar/latest.json al generar el paquete.

$ErrorActionPreference = 'Stop'
$Folder    = Join-Path $env:LOCALAPPDATA 'CodeRED\shalom-recordar'
$LatestUrl = 'https://platform.codered.lat/ext/shalom-recordar/latest.json'

function Get-InstalledVersion {
    $m = Join-Path $Folder 'manifest.json'
    if (-not (Test-Path $m)) { return '0.0.0' }
    try { return (Get-Content $m -Raw | ConvertFrom-Json).version } catch { return '0.0.0' }
}

function Test-Newer($remote, $local) {
    try { return [version] $remote -gt [version] $local } catch { return $remote -ne $local }
}

# Sin red: se reintenta en la proxima ejecucion de la tarea.
try { $latest = Invoke-RestMethod -Uri $LatestUrl -TimeoutSec 30 } catch { return }
if (-not $latest.version -or -not $latest.zip) { return }
if (-not (Test-Newer $latest.version (Get-InstalledVersion))) { return }

$tmpZip = Join-Path $env:TEMP ("shalom-recordar-" + $latest.version + ".zip")
$tmpDir = Join-Path $env:TEMP ("shalom-recordar-" + $latest.version)
Invoke-WebRequest -Uri $latest.zip -OutFile $tmpZip -TimeoutSec 120

if (Test-Path $tmpDir) { Remove-Item -LiteralPath $tmpDir -Recurse -Force }
Expand-Archive -LiteralPath $tmpZip -DestinationPath $tmpDir -Force

# Validacion minima antes de tocar la carpeta en uso: el paquete debe traer un
# manifest.json legible. Asi un zip corrupto o una respuesta HTML no deja la
# extension inservible.
$newManifest = Join-Path $tmpDir 'manifest.json'
if (-not (Test-Path $newManifest)) { Remove-Item -LiteralPath $tmpZip -Force -ErrorAction SilentlyContinue; return }
try { [void] (Get-Content $newManifest -Raw | ConvertFrom-Json) } catch { return }

New-Item -ItemType Directory -Path $Folder -Force | Out-Null
Get-ChildItem -LiteralPath $Folder -Force | Remove-Item -Recurse -Force
Copy-Item -Path (Join-Path $tmpDir '*') -Destination $Folder -Recurse -Force

Remove-Item -LiteralPath $tmpZip -Force -ErrorAction SilentlyContinue
Remove-Item -LiteralPath $tmpDir -Recurse -Force -ErrorAction SilentlyContinue
