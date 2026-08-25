# Ficha de Chrome Web Store — Buscador Shalom Control 2.7.2

Texto listo para pegar en el panel de desarrollador. Revisar antes de cada
publicación por si cambian permisos o alcance.

---

## Nombre

```
Buscador Shalom Control
```

## Descripción breve (132 caracteres máximo)

Usada también como `description` del manifest.

```
Busca agencias Shalom por nombre, código o ubicación y selecciona el destino en un clic, sin salir de Shalom Control.
```

*115 caracteres.*

## Descripción completa

```
Buscador Shalom Control añade un buscador de agencias dentro de Shalom Control y rellena por ti la agencia de destino.

El buscador propio de Shalom Control solo filtra por departamento, provincia o distrito, y hay que escribir el principio exacto del nombre. Si conoces la agencia pero no recuerdas a qué provincia pertenece, toca ir probando. Esta extensión resuelve justo eso: escribes lo que recuerdas —el nombre de la agencia, su código o la ciudad— y la encuentra al instante.

QUÉ HACE

• Busca por nombre de agencia, código, dirección o ubicación, con o sin tildes. También encuentra agencias por su nombre anterior, útil cuando una sede cambió de nombre y el equipo aún la conoce por el viejo.
• Selecciona la agencia de destino en el formulario con un clic. Funciona tanto en el módulo clásico de orden de servicio como en la interfaz nueva.
• Muestra dirección, horarios, teléfono y categoría de cada agencia antes de elegirla, y avisa cuando una agencia se ha mudado o no está disponible.
• Distingue el canal Terrestre y Aéreo y busca en las agencias que corresponden.
• Funciona sin conexión: tras la primera sincronización el catálogo queda guardado en tu equipo y la búsqueda es local e instantánea.
• Control horario opcional: tu organización puede definir en qué franjas se permite operar en cada módulo, y la extensión lo aplica en el navegador.

CÓMO SE USA

1. Instala la extensión y solicita un token de acceso desde CodeRED Platform.
2. Pega el token en las opciones de la extensión.
3. Entra en Shalom Control: el buscador aparece integrado en la página.

El catálogo se actualiza solo cada 24 horas, y puedes forzar la sincronización desde el popup.

REQUISITOS

Necesitas una cuenta de CodeRED Platform y un token de acceso válido. Sin token la extensión no sincroniza ni muestra agencias. Es una herramienta de uso interno para equipos que operan con Shalom Control.

PRIVACIDAD

• No recoge datos personales ni de navegación.
• No inyecta anuncios ni modifica el contenido de las páginas más allá del buscador y, si tu organización lo activa, el aviso de horario.
• Solo se ejecuta en shalomcontrol.com y solo se comunica con CodeRED Platform.
• El token se guarda en el almacenamiento local de la extensión, al que no accede ninguna página web, y nunca se entrega al sitio: solo el proceso en segundo plano lo usa para hablar con CodeRED Platform.
• No es un producto oficial de Shalom Empresarial.
```

## Categoría

Productividad · Herramientas de trabajo

## Justificación de permisos

Rellenar en la pestaña *Privacy practices*. Cada campo del formulario:

| Permiso | Justificación |
| --- | --- |
| `storage` | Guarda el catálogo de agencias descargado y el token de acceso. Sin él la extensión tendría que descargar el catálogo entero en cada búsqueda y pedir el token en cada sesión. |
| `alarms` | Programa la sincronización periódica del catálogo (cada 24 h) y la comprobación de las reglas de horario, sin mantener procesos activos. |
| `tabs` | Abre la página de solicitud de token en una pestaña nueva y determina a qué módulo de Shalom Control corresponde la pestaña activa para mostrar su estado en el popup. No se lee el historial ni el contenido de otras pestañas. |
| `https://*.shalomcontrol.com/*` | Es la aplicación donde se inserta el buscador. Se necesita el dominio completo porque los módulos viven en subdominios y rutas distintos (`sysnewos`, `sysprovincia2`, …) y la organización decide desde su panel en qué rutas se aplica el control horario. |
| `https://platform.codered.lat/*` | Servidor propio del que se descargan el catálogo de agencias y la configuración. Es el único origen al que la extensión envía peticiones. |
| `https://platform.codered.host/*` | Dominio anterior del mismo servidor, declarado solo por compatibilidad con instalaciones que aún lo tengan configurado. |

### Uso de código remoto

No. Todo el código se empaqueta en la extensión. Del servidor solo se
descargan datos (JSON), nunca scripts.

### Declaración de uso de datos

- **No** se venden ni transfieren datos a terceros.
- **No** se usan para fines ajenos a la funcionalidad principal.
- **No** se usan para determinar solvencia ni para préstamos.
- Datos manejados: el token de acceso y el catálogo de agencias, ambos
  almacenados localmente en el equipo del usuario mediante `chrome.storage.local`.
- No se recoge información personal, de salud, financiera, de ubicación,
  de actividad web ni de comunicaciones personales.

## Notas de la versión 2.7.2

```
• Compatibilidad con la nueva interfaz de orden de servicio: el buscador se integra y selecciona la agencia de destino también ahí.
• La insignia del buscador muestra únicamente el canal activo, Terrestre o Aéreo.
• Menos ruido en la consola del navegador.
• Correcciones al actualizar la extensión con pestañas de Shalom Control abiertas.
```
