# Instalar con "Cargar sin empaquetar" (equipos no gestionados)

En equipos **no gestionados** (sin dominio ni gestión de navegador en la nube),
Chrome, Edge y Brave **ignoran a propósito** las extensiones autoalojadas
forzadas por registro. Es una medida de seguridad contra malware que se
autoinstala; no es un fallo del paquete. Se comprobó: con la política puesta,
el navegador ni siquiera contacta el servidor.

Por eso, en estos equipos la única forma sin la Chrome Web Store es cargarla en
modo desarrollador. La extensión lleva una clave fija en el manifest, así que su
id es siempre `hfamlncmfjknhmoanoebbjjkgedkdghi` en todos los equipos, aunque se
mueva la carpeta.

## Recomendado: instalador con actualización automática

Copia al equipo estos tres archivos **juntos** (están en `release/`) y haz doble
clic en `Instalar-Desempaquetada.cmd`:

```
Instalar-Desempaquetada.cmd
instalar-desempaquetada.ps1
actualizar.ps1
```

El instalador (sin admin):

- descarga la extensión a `%LOCALAPPDATA%\CodeRED\shalom-recordar`,
- registra una **Tarea Programada** que la mantiene al día (al iniciar sesión y a
  diario): comprueba la versión publicada y, si hay una nueva, sobrescribe la
  carpeta. El navegador la adopta al reabrirse,
- abre la carpeta y te recuerda el único paso manual: **Cargar sin empaquetar**
  esa carpeta una vez (pasos 2-4 de abajo).

## Pasos manuales (o si prefieres sin actualización automática)

1. Descomprime `shalom-recordar-unpacked-<versión>.zip` en una carpeta
   **permanente** del equipo, p. ej. `C:\CodeRED\shalom-recordar\`.
   (No la borres ni la muevas después: la extensión se carga desde ahí.)

2. Abre las extensiones del navegador:
   - Chrome: `chrome://extensions`
   - Edge:   `edge://extensions`
   - Brave:  `brave://extensions`

3. Activa **Modo de desarrollador** (interruptor arriba a la derecha, o abajo a
   la izquierda en Edge).

4. Pulsa **Cargar sin empaquetar** («Cargar desempaquetada») y elige la carpeta
   `C:\CodeRED\shalom-recordar\` (la que contiene `manifest.json`).

La extensión "Registro de Actividad Shalom" queda instalada y funcionando.

## Cosas a tener en cuenta

- **Aviso al iniciar:** en cada arranque el navegador muestra un aviso de
  «Desactivar las extensiones en modo de desarrollador». Hay que pulsar
  «Conservar» (o cerrar el aviso). No se puede evitar en modo desarrollador.
- **El usuario puede quitarla** desde la página de extensiones. Si necesitas que
  quede fija y no se pueda quitar, hace falta que el equipo esté *gestionado*
  (ver `instalacion-empresa.md`, opción Cloud Management).
- **Actualización:** si usaste el instalador con Tarea Programada, la carpeta se
  mantiene al día sola y la versión nueva se aplica al reabrir el navegador. Si
  hiciste la instalación manual, reemplaza el contenido de la carpeta por el del
  zip nuevo (o quítala y vuelve a cargarla).

## ¿Y el force-install del registro?

Sirve, pero solo en equipos **gestionados** (unidos a dominio o enrolados en
Chrome Browser Cloud Management / Intune). En esos equipos, el instalador y los
`.reg` de `instalacion-empresa.md` funcionan tal cual y dejan la extensión fija.
