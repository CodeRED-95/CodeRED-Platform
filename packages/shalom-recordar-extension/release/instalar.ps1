# Instalador de "Registro de Actividad Shalom".
#
# Detecta los navegadores Chromium instalados, deja elegir en cuáles instalar
# la extensión y escribe la política que la fuerza (ExtensionInstallForcelist).
# No requiere Chrome Web Store ni modo desarrollador.
#
# pack-crx.mjs reemplaza hfamlncmfjknhmoanoebbjjkgedkdghi y https://platform.codered.lat/ext/shalom-recordar/updates.xml al generar el paquete.

$ErrorActionPreference = 'Stop'
$ExtensionId = 'hfamlncmfjknhmoanoebbjjkgedkdghi'
$UpdateUrl   = 'https://platform.codered.lat/ext/shalom-recordar/updates.xml'
$ForceValue  = "$ExtensionId;$UpdateUrl"

# --- Elevacion: escribir en HKLM requiere administrador ----------------------
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()
    ).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Write-Host 'Solicitando permisos de administrador...' -ForegroundColor Yellow
    Start-Process -FilePath 'powershell.exe' `
        -ArgumentList @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', "`"$PSCommandPath`"") `
        -Verb RunAs
    return
}

# --- Catalogo de navegadores -------------------------------------------------
# policyKey: rama bajo HKLM\SOFTWARE\Policies. exes: rutas donde buscar el
# ejecutable para saber si esta instalado.
$browsers = @(
    @{ Name = 'Google Chrome'; PolicyKey = 'Google\Chrome';            Exe = 'chrome.exe';  Paths = @(
        "$env:ProgramFiles\Google\Chrome\Application\chrome.exe",
        "${env:ProgramFiles(x86)}\Google\Chrome\Application\chrome.exe") }
    @{ Name = 'Microsoft Edge'; PolicyKey = 'Microsoft\Edge';          Exe = 'msedge.exe';  Paths = @(
        "${env:ProgramFiles(x86)}\Microsoft\Edge\Application\msedge.exe",
        "$env:ProgramFiles\Microsoft\Edge\Application\msedge.exe") }
    @{ Name = 'Brave';          PolicyKey = 'BraveSoftware\Brave';     Exe = 'brave.exe';   Paths = @(
        "$env:ProgramFiles\BraveSoftware\Brave-Browser\Application\brave.exe",
        "${env:ProgramFiles(x86)}\BraveSoftware\Brave-Browser\Application\brave.exe",
        "$env:LOCALAPPDATA\BraveSoftware\Brave-Browser\Application\brave.exe") }
    @{ Name = 'Opera';          PolicyKey = 'Opera Software\Opera';    Exe = 'opera.exe';   Paths = @(
        "$env:LOCALAPPDATA\Programs\Opera\opera.exe",
        "$env:ProgramFiles\Opera\opera.exe") }
    @{ Name = 'Vivaldi';        PolicyKey = 'Vivaldi';                 Exe = 'vivaldi.exe'; Paths = @(
        "$env:LOCALAPPDATA\Vivaldi\Application\vivaldi.exe",
        "$env:ProgramFiles\Vivaldi\Application\vivaldi.exe") }
)

function Test-BrowserInstalled($browser) {
    foreach ($p in $browser.Paths) { if (Test-Path $p) { return $true } }
    $appPaths = @(
        "HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\App Paths\$($browser.Exe)",
        "HKLM:\SOFTWARE\WOW6432Node\Microsoft\Windows\CurrentVersion\App Paths\$($browser.Exe)")
    foreach ($ap in $appPaths) { if (Test-Path $ap) { return $true } }
    return $false
}

$installed = @($browsers | Where-Object { Test-BrowserInstalled $_ })

if ($installed.Count -eq 0) {
    Write-Host ''
    Write-Host 'No se detecto ningun navegador Chromium compatible (Chrome, Edge, Brave, Opera, Vivaldi).' -ForegroundColor Red
    Write-Host 'Instala uno y vuelve a ejecutar este instalador.'
    Read-Host 'Pulsa Enter para salir'
    return
}

# --- Politica: escribir / quitar ---------------------------------------------
function Get-PolicyPath($browser) { "HKLM:\SOFTWARE\Policies\$($browser.PolicyKey)\ExtensionInstallForcelist" }

function Install-ForBrowser($browser) {
    $path = Get-PolicyPath $browser
    if (-not (Test-Path $path)) { New-Item -Path $path -Force | Out-Null }

    $key = Get-Item -Path $path
    # Ya presente: no duplicar.
    foreach ($name in $key.GetValueNames()) {
        if ($key.GetValue($name) -eq $ForceValue) {
            Write-Host "  $($browser.Name): ya estaba instalada." -ForegroundColor DarkGray
            return
        }
    }
    # Primer indice numerico libre.
    $index = 1
    while ($null -ne $key.GetValue("$index")) { $index++ }
    New-ItemProperty -Path $path -Name "$index" -Value $ForceValue -PropertyType String -Force | Out-Null
    Write-Host "  $($browser.Name): instalada." -ForegroundColor Green
}

function Uninstall-ForBrowser($browser) {
    $path = Get-PolicyPath $browser
    if (-not (Test-Path $path)) { return }
    $key = Get-Item -Path $path
    $removed = $false
    foreach ($name in $key.GetValueNames()) {
        $val = [string] $key.GetValue($name)
        if ($val -eq $ForceValue -or $val -like "$ExtensionId;*") {
            Remove-ItemProperty -Path $path -Name $name -Force
            $removed = $true
        }
    }
    if ($removed) { Write-Host "  $($browser.Name): desinstalada." -ForegroundColor Yellow }
}

# --- Menu --------------------------------------------------------------------
function Show-Menu {
    Write-Host ''
    Write-Host '=== Registro de Actividad Shalom - Instalador ===' -ForegroundColor Cyan
    Write-Host 'Navegadores detectados:'
    for ($i = 0; $i -lt $installed.Count; $i++) {
        Write-Host ("  {0}) {1}" -f ($i + 1), $installed[$i].Name)
    }
    Write-Host ''
    Write-Host '  T) Instalar en TODOS'
    Write-Host '  D) Desinstalar (elige despues)'
    Write-Host '  S) Salir'
    Write-Host ''
    Write-Host 'Elige uno o varios por numero separados por coma (ej. 1,3):'
}

function Read-Selection {
    $raw = (Read-Host 'Opcion').Trim()
    if ($raw -match '^[Ss]$') { return @{ Action = 'exit' } }
    if ($raw -match '^[Tt]$') { return @{ Action = 'install'; Items = $installed } }
    if ($raw -match '^[Dd]$') { return @{ Action = 'uninstall-menu' } }

    $items = @()
    foreach ($tok in ($raw -split ',')) {
        $n = 0
        if ([int]::TryParse($tok.Trim(), [ref] $n) -and $n -ge 1 -and $n -le $installed.Count) {
            $items += $installed[$n - 1]
        }
    }
    if ($items.Count -eq 0) { return @{ Action = 'invalid' } }
    return @{ Action = 'install'; Items = $items }
}

Show-Menu
$sel = Read-Selection

switch ($sel.Action) {
    'exit'    { return }
    'invalid' { Write-Host 'Opcion no valida.' -ForegroundColor Red; Read-Host 'Enter para salir'; return }
    'install' {
        Write-Host ''
        foreach ($b in $sel.Items) { Install-ForBrowser $b }
        Write-Host ''
        Write-Host 'Listo. Cierra y vuelve a abrir el navegador para que aparezca la extension.' -ForegroundColor Cyan
    }
    'uninstall-menu' {
        Write-Host ''
        Write-Host 'Desinstalar de:'
        for ($i = 0; $i -lt $installed.Count; $i++) { Write-Host ("  {0}) {1}" -f ($i + 1), $installed[$i].Name) }
        Write-Host '  T) Todos'
        $raw = (Read-Host 'Opcion').Trim()
        $targets = @()
        if ($raw -match '^[Tt]$') { $targets = $installed }
        else {
            foreach ($tok in ($raw -split ',')) {
                $n = 0
                if ([int]::TryParse($tok.Trim(), [ref] $n) -and $n -ge 1 -and $n -le $installed.Count) { $targets += $installed[$n - 1] }
            }
        }
        Write-Host ''
        foreach ($b in $targets) { Uninstall-ForBrowser $b }
        Write-Host ''
        Write-Host 'Cierra y vuelve a abrir el navegador para aplicar el cambio.' -ForegroundColor Cyan
    }
}

Read-Host 'Pulsa Enter para salir'
