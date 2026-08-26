# Instalar la extensión sin la Chrome Web Store

Esta extensión no puede publicarse en la Chrome Web Store. Se instala forzándola
por política de empresa desde un `.crx` firmado que tú alojas. Así queda
instalada, activa y fija —el usuario no la puede desactivar ni quitar— y **no
requiere el modo desarrollador** («cargar sin empaquetar»).

Sirve para tus propios equipos Windows, con permiso de administrador.

## Cómo funciona

- El `.crx` va firmado con una clave RSA propia y estable
  (`packaging/shalom-recordar.pem`). De esa clave se deriva el **id** de la
  extensión, que no cambia entre versiones.
- Chrome lee una política del registro (`ExtensionInstallForcelist`) que apunta a
  un `updates.xml` alojado por ti. Ese manifiesto indica dónde está el `.crx` y
  qué versión es.
- En cada arranque Chrome comprueba el `updates.xml`: si hay una versión nueva,
  la instala sola.

## Requisitos

- Los archivos `shalom-recordar-extension-<versión>.crx` y `updates.xml`
  accesibles por HTTPS. El despliegue de la Plataforma ya los publica en
  `https://platform.codered.lat/ext/shalom-recordar/`.
- Permiso de administrador en cada PC (para escribir la política una vez).

## Paso a paso

### 1. Generar el paquete (una vez por versión, en tu equipo de desarrollo)

```bash
cd packages/shalom-recordar-extension
npm run pack:crx
```

Genera en `release/`:

- `shalom-recordar-extension-<versión>.crx` — la extensión firmada.
- `updates.xml` — el manifiesto de actualización.
- `force-install-shalom-recordar.reg` — la política de registro, con el id ya puesto.

Y copia el `.crx` y el `updates.xml` a `public/ext/shalom-recordar/` para que el
despliegue los sirva.

> La clave `packaging/shalom-recordar.pem` **no se sube a git** y no debe
> perderse: si cambia, cambia el id y las instalaciones existentes dejan de
> reconocer las actualizaciones. Guárdala en un lugar seguro.

### 2. Publicar los archivos

Haz el despliegue normal de la Plataforma (`git pull && ./update.sh`). Comprueba
que responden:

```
https://platform.codered.lat/ext/shalom-recordar/updates.xml   → 200 (XML)
https://platform.codered.lat/ext/shalom-recordar/shalom-recordar-extension-<versión>.crx → 200
```

### 3. Instalar en cada PC (una sola vez)

**Recomendado — instalador interactivo.** Copia al equipo estos dos archivos,
**juntos en la misma carpeta**:

```
Instalar-Shalom-Recordar.cmd
instalar.ps1
```

Doble clic en `Instalar-Shalom-Recordar.cmd`. El instalador:

- pide permisos de administrador,
- detecta los navegadores Chromium instalados (Chrome, Edge, Brave, Opera, Vivaldi),
- te deja **elegir** en cuáles instalar (uno, varios por número `1,3`, o `T` para todos),
- también desinstala (opción `D`).

El mismo `.crx` y el mismo id valen para todos esos navegadores; solo cambia la
rama del registro, y de eso se encarga el instalador.

**Alternativa manual — un `.reg` por navegador.** Si prefieres no usar el
instalador, en `release/` tienes `force-install-chrome.reg`,
`force-install-edge.reg`, `force-install-brave.reg`, `force-install-opera.reg` y
`force-install-vivaldi.reg`. Ejecuta como administrador el del navegador que
quieras:

```bat
reg import force-install-brave.reg
```

Cierra y vuelve a abrir Chrome. La extensión aparece instalada y fija. Puedes
comprobarlo en `chrome://policy` (debe verse `ExtensionInstallForcelist`) y en
`chrome://extensions` (aparecerá como «Instalada por tu organización»).

El `.reg` escribe una sola clave:

```
HKEY_LOCAL_MACHINE\SOFTWARE\Policies\Google\Chrome\ExtensionInstallForcelist
  "1" = "<id>;https://platform.codered.lat/ext/shalom-recordar/updates.xml"
```

Si ya usas ese valor `"1"` para otra extensión, cambia el número (`"2"`, `"3"`…).

## Actualizar a una versión nueva

1. `npm run pack:crx` (genera el nuevo `.crx` y actualiza `updates.xml`).
2. Despliega la Plataforma.
3. No hay que tocar los PCs: Chrome detecta la versión nueva por el `updates.xml`
   y actualiza solo. El `.reg` no cambia (el id es estable).

## Desinstalar

Borra la clave del registro y reinicia Chrome:

```bat
reg delete "HKLM\SOFTWARE\Policies\Google\Chrome\ExtensionInstallForcelist" /v "1" /f
```

## Alternativa sin servidor (aún menos pasos, si no quieres alojar nada)

Puedes servir el `updates.xml` y el `.crx` desde el propio disco con rutas
`file:///`. Copia ambos a, por ejemplo, `C:\CodeRED\ext\`, edita el `updates.xml`
para que `codebase` apunte a `file:///C:/CodeRED/ext/shalom-recordar-extension-<versión>.crx`,
y en el `.reg` usa `...;file:///C:/CodeRED/ext/updates.xml`. Útil para un único
equipo aislado; para varios, el hosting HTTPS es más cómodo.
