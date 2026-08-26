@echo off
REM Instalador para equipos NO gestionados, con actualizacion automatica.
REM Doble clic aqui. No requiere administrador.
REM instalar-desempaquetada.ps1 y actualizar.ps1 deben estar en esta carpeta.
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0instalar-desempaquetada.ps1"
