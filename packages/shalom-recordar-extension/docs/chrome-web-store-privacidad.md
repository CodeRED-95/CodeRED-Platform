# Chrome Web Store — pestaña Privacidad

Textos y URLs que hay que dejar cargados en el panel de desarrollador para
"Registro de Actividad Shalom" (ID `ljjmfhhpjhihdfpgcnaalmkmiiojliak`).

El rechazo de la 2.9.8 fue **Purple Nickel** — *"El vínculo a la política de
privacidad no funciona o no está disponible"*. Se corrige publicando la página
propia de la extensión y declarándola aquí.

## URL de la política de privacidad

```
https://platform.codered.lat/privacy/registro-actividad-shalom
```

Requisitos que ya cumple: responde 200 sin autenticación, es HTTPS, no
redirige a login y menciona a la extensión por su nombre.

> No usar `/privacy/buscador-shalom`: describe otra extensión y otro
> tratamiento de datos.

## URL de soporte (campo "Sitio web de asistencia")

```
https://platform.codered.lat/support/registro-actividad-shalom
```

## Propósito único

> Dejar constancia, para auditoría interna de la empresa, de la actividad que
> el personal autorizado registra en el sistema Shalom Control
> (`sysprovincia2.shalomcontrol.com`), y sincronizar ese historial con CodeRED
> Platform.

## Justificación de permisos

| Permiso | Justificación |
| --- | --- |
| `storage` | Guardar la sesión del usuario, el token de sincronización y las preferencias de la extensión. |
| `unlimitedStorage` | El historial local cifrado supera el límite de `chrome.storage.local` en puestos con alto volumen diario; se conserva en IndexedDB hasta sincronizarse. |
| `alarms` | Programar la sincronización automática diaria con CodeRED Platform. |
| Host `https://platform.codered.lat/*` | Único servidor al que la extensión envía los registros y contra el que autentica al usuario. |
| Content script en `https://sysprovincia2.shalomcontrol.com/*` | Único sitio donde se registra la actividad. La extensión no lee ninguna otra página. |
| Código remoto | **No se usa.** Todo el código va dentro del paquete. |

## Declaración de uso de datos

Marcar:

- **Información de identificación personal** — sí (documentos DNI / CE / RUC y
  correo de la cuenta corporativa).
- **Actividad del usuario** — sí (los datos que el operador introduce en el
  formulario de Shalom Control).

No marcar: estado de salud, información financiera, autenticación de terceros,
comunicaciones personales, ubicación, historial de navegación de sitios web.

## Certificaciones

Las tres se pueden marcar:

- No vendo ni transfiero datos de usuarios a terceros ajenos a los casos de uso aprobados.
- No uso ni transfiero datos de usuarios con fines no relacionados con la funcionalidad única del artículo.
- No uso ni transfiero datos de usuarios para determinar solvencia ni con fines de préstamo.

## Antes de reenviar

1. Desplegar la plataforma (`./update.sh` en el servidor) y comprobar que
   la URL de privacidad devuelve 200 en una ventana de incógnito.
2. Subir `release/shalom-recordar-store-<version>.zip` (manifiesto sin `key`).
3. Rellenar los campos de arriba y enviar el borrador a revisión.
