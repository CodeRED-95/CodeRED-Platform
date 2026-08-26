# Instalador de "Registro de Actividad Shalom" para equipos NO gestionados,
# con actualizacion automatica.
#
# 1. Descarga la version actual a una carpeta fija.
# 2. Registra una Tarea Programada que mantiene esa carpeta al dia.
# 3. Te indica el unico paso manual: "Cargar sin empaquetar" esa carpeta una vez.
#
# No necesita permisos de administrador: todo va en el perfil del usuario.
# pack-crx.mjs reemplaza __FOLDER_REL__ y __LATEST_URL__.

$ErrorActionPreference = 'Stop'
$Folder    = Join-Path $env:LOCALAPPDATA '__FOLDER_REL__'
$LatestUrl = '__LATEST_URL__'
$Base      = Split-Path -Parent $Folder
$Updater   = Join-Path $Base 'actualizar.ps1'
$TaskName  = 'CodeRED - Actualizar Shalom Recordar'
$SelfDir   = Split-Path -Parent $PSCommandPath

Write-Host '=== Instalando Registro de Actividad Shalom ===' -ForegroundColor Cyan

# --- 1. Descargar e instalar la version actual -------------------------------
New-Item -ItemType Directory -Path $Folder -Force | Out-Null
$latest = Invoke-RestMethod -Uri $LatestUrl -TimeoutSec 30
$tmpZip = Join-Path $env:TEMP 'shalom-recordar-latest.zip'
Invoke-WebRequest -Uri $latest.zip -OutFile $tmpZip -TimeoutSec 120
Get-ChildItem -LiteralPath $Folder -Force -ErrorAction SilentlyContinue | Remove-Item -Recurse -Force
Expand-Archive -LiteralPath $tmpZip -DestinationPath $Folder -Force
Remove-Item -LiteralPath $tmpZip -Force -ErrorAction SilentlyContinue
Write-Host ("  Version {0} instalada en {1}" -f $latest.version, $Folder) -ForegroundColor Green

# --- 2. Dejar el actualizador y registrar la Tarea Programada ----------------
$srcUpdater = Join-Path $SelfDir 'actualizar.ps1'
if (Test-Path $srcUpdater) { Copy-Item $srcUpdater $Updater -Force }

if (Test-Path $Updater) {
    $action   = New-ScheduledTaskAction -Execute 'powershell.exe' `
        -Argument ("-NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File `"{0}`"" -f $Updater)
    $atLogon  = New-ScheduledTaskTrigger -AtLogOn
    $daily    = New-ScheduledTaskTrigger -Daily -At 9am
    $settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -DontStopOnIdleEnd
    Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger @($atLogon, $daily) `
        -Settings $settings -Description 'Mantiene al dia la extension Registro de Actividad Shalom.' -Force | Out-Null
    Write-Host '  Actualizacion automatica programada (al iniciar sesion y a diario).' -ForegroundColor Green
} else {
    Write-Host '  AVISO: no se encontro actualizar.ps1 junto a este instalador; sin auto-actualizacion.' -ForegroundColor Yellow
}

# --- 3. Instruccion para el unico paso manual --------------------------------
Write-Host ''
Write-Host 'FALTA UN PASO (solo la primera vez):' -ForegroundColor Cyan
Write-Host '  1. Abre las extensiones de tu navegador:'
Write-Host '       Chrome  ->  chrome://extensions'
Write-Host '       Edge    ->  edge://extensions'
Write-Host '       Brave   ->  brave://extensions'
Write-Host '  2. Activa "Modo de desarrollador".'
Write-Host '  3. Pulsa "Cargar sin empaquetar" y elige esta carpeta:'
Write-Host ("       {0}" -f $Folder) -ForegroundColor White
Write-Host ''
Write-Host 'Las actualizaciones se aplican solas; veras la version nueva al reabrir el navegador.' -ForegroundColor Cyan

# Abrir la carpeta para que sea facil arrastrarla al navegador.
Start-Process explorer.exe $Folder

Read-Host 'Pulsa Enter para salir'
