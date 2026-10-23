# Sincronizador de agencias Shalom

Extensión Chrome independiente del buscador y de Shalom Recordar. Lee la lista
nativa de destinos terrestres de Shalom, compara con Platform y sincroniza
exclusivamente el estado de las agencias identificadas.

## Instalar en Chrome

1. Compila con `npm ci`, `npm test` y `npm run build` desde esta carpeta.
   El repositorio raíz debe tener sus dependencias instaladas (`npm ci`): el
   build reutiliza sus fuentes locales y el símbolo oficial de CodeRED.
2. Abre `chrome://extensions` y activa **Modo de desarrollador**.
3. Elige **Cargar descomprimida** y selecciona la carpeta `dist` de este paquete.
   No selecciones `codered-chrome-extension` ni `shalom-recordar-extension`.
4. Recarga la pestaña de Shalom. Abre el nuevo sincronizador desde su icono.

`npm run package` genera `releases/codered-shalom-agency-sync-1.0.0.zip`, que
contiene el manifiesto en la raíz. Descomprime el ZIP antes de cargarlo en Chrome.
No es una publicación en Chrome Web Store ni una actualización del buscador.

## Comparar y aplicar

1. Inicia sesión en el panel de la extensión con tu cuenta administradora de
   Platform. La cuenta debe estar activa y verificada, tener `platform.access`,
   `agencies.view` y `agencies.manage_status`, y ser `super-admin` o conservar el
   rol administrativo heredado `admin`. Un editor o un token técnico no bastan.
2. En Shalom, entra a **PRODUCTOS**, selecciona **TERRESTRE** y abre el selector
   de destino sin escribir en su buscador. El destino debe estar habilitado por
   Shalom; si es necesario, agrega tú un producto al borrador, sin generar la orden.
3. Pulsa **Capturar lista nativa** en el sincronizador. Si el selector ya está
   abierto no se altera; si está cerrado, se abre. No se elige ningún destino.
4. Pulsa **Comparar estados**. Revisa agencias por activar, por desactivar y las
   pendientes de revisión. El origen visible en Shalom queda protegido.
5. Pulsa **Aplicar cambios en Platform** y confirma los conteos mostrados.
6. Comprueba el aviso verde persistente **Sincronización completada** con fecha
   de Perú y número de operación. Verifica los estados en el panel de Platform;
   cada cambio genera auditoría atribuida a tu usuario.

Una comparación no cambia datos. No se crean agencias ni se corrigen nombres,
direcciones o identificadores. El estado es global en Platform; las agencias
ausentes sin referencia terrestre (incluidas las exclusivamente aéreas) se
dejan para revisión y no se desactivan.

## Automatización

Activa **Aplicar capturas completas automáticamente** después de iniciar sesión.
Debes confirmar que ese modo podrá modificar Platform. A partir de entonces,
abrir una lista terrestre completa y sin filtro dispara captura, comparación y
aplicación si el servidor no encuentra bloqueos. Las desconocidas y ambiguas se
muestran para revisión, sin crear ni modificar esos registros.

Es automatización por captura, no un rastreador en segundo plano: no abre Shalom
periódicamente ni evita iniciar sesión allí. Una lista sin cambios se captura
una sola vez por carga de la página; **Capturar lista nativa** permite repetirla.
La automatización se apaga al cerrar sesión o al cerrar completamente Chrome.
Un cambio de permisos impide aplicar aunque la comparación siga visible.

## Protecciones y límites

- Solo acepta el selector de destino de
  `https://sysnewos.shalomcontrol.com/service-order/items` y transporte terrestre.
- Exige buscador vacío, al menos 100 opciones, índices consecutivos y dos
  lecturas estables al final del desplazamiento de la lista, sin indicadores
  de carga. Desplaza solo esa lista y restaura su posición; no elige destinos.
  No acepta listas aéreas, filtradas o de formato desconocido.
- Empareja ubicación completa y nombre normalizados (sin diferencias por
  acentos, mayúsculas o espacios). Acepta un nombre anterior solo si es único.
- Conserva el origen. No modifica coincidencias ambiguas, agencias trasladadas
  ni ausentes sin referencia terrestre. Las desconocidas no se importan.
- Bloquea si coincide menos del 80 % de la captura, el origen no es único o se
  desactivaría más del 10 % del catálogo terrestre activo.
- La captura y comparación caducan a los 10 minutos. Un catálogo modificado
  obliga a comparar otra vez. Reintentar la misma aplicación devuelve su recibo
  mientras la operación siga disponible en caché; no es recuperación indefinida.
- La captura refleja lo que Shalom ofrece a la sesión y origen actuales; no
  prueba que Shalom tenga el mismo catálogo para todas las sucursales. Si la
  lista es restringida por esa cuenta, no actives automatización global.
- Los límites reducen el riesgo de una captura parcial; no son una firma ni una
  certificación del catálogo de Shalom. Revisa el primer resultado antes de
  habilitar aplicaciones automáticas.

## Sesión y privacidad

El login, renovación y cierre usan la autenticación oficial de Platform. La
contraseña no se guarda; los tokens solo se conservan en `chrome.storage.session`
con acceso restringido a contextos de la extensión. Nunca llegan al content
script ni a Shalom. No se usa `storage.local`, sincronización de Chrome, analítica
ni servidores externos adicionales. Al cerrar completamente Chrome, los tokens
locales desaparecen; puedes revocar sesiones restantes desde Platform.

Se envían a Platform nombres/ubicaciones de destinos, origen y fecha de captura.
No se capturan clientes, productos, pagos, órdenes ni el nombre del operador de
Shalom. Permisos: `storage` y acceso a los dos dominios estrictamente necesarios.

Apagar la automatización es local y no requiere conexión. Si el servidor no
responde, **Cerrar sesión** apaga la automatización y conserva la sesión local
para volver a intentar su revocación; también puedes revocarla desde Platform.
Si tu correo requiere verificación, completa ese paso en Platform y reintenta.

Si se pierde una respuesta al aplicar, se muestra **Resultado pendiente de
comprobar** y se apaga la automatización. Usa **Comprobar resultado** para pedir
el recibo sin repetir cambios. Si no existe recibo, comprueba estados y auditoría
en Platform antes de descartar manualmente el pendiente. Nunca interpretes un
timeout como prueba de que no se aplicó nada.

## Desarrollo y comprobación

```powershell
npm ci
npm test
npm run build
npm run package
npm run preview
```

La vista `http://127.0.0.1:4175` es solo visual: no puede iniciar sesión ni
sincronizar fuera del contexto de extensión. El backend requerido está descrito
en [API de sincronización](../../docs/SHALOM_NATIVE_AGENCY_SYNC.md).

Desde la raíz del repositorio, ejecuta las pruebas del backend dentro del
servicio Compose existente: `docker compose exec -T app php artisan test
tests/Feature/NativeAgencySyncTest.php`. Se usa `codered_testing`, nunca el
catálogo productivo. Completa la validación con
`docker compose exec -T app composer verify`.
