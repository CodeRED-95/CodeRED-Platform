@echo off
REM Lanzador del instalador. Doble clic aqui.
REM Ejecuta instalar.ps1 (que esta junto a este archivo) saltando la politica
REM de ejecucion de PowerShell; el propio script pide permisos de administrador.
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0instalar.ps1"
