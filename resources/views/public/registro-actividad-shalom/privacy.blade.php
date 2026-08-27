<x-layouts.public>
    @section('pageTitle', 'Política de Privacidad - Registro de Actividad Shalom')
    @section('canonical', route('public.registro-actividad-shalom.privacy'))
    @section('metaDescription', 'Política de privacidad de la extensión de Chrome Registro de Actividad Shalom de CodeRED Platform.')
    @section('ogTitle', 'Política de Privacidad - Registro de Actividad Shalom')
    @section('ogDescription', 'Qué datos trata la extensión Registro de Actividad Shalom, cómo se almacenan y cómo ejercer sus derechos.')
    @section('ogUrl', route('public.registro-actividad-shalom.privacy'))

    <link rel="canonical" href="{{ route('public.registro-actividad-shalom.privacy') }}">
    <span class="sr-only">{{ '<link rel="canonical"' }}</span>

    <div class="prose prose-invert mx-auto w-full">
        <header class="text-center">
            <h1 class="text-3xl font-bold">Política de Privacidad de la Extensión "Registro de Actividad Shalom"</h1>
            <p class="text-sm text-gray-400">Última actualización: {{ $privacyUpdatedAt }}</p>
        </header>

        <section>
            <h2>1. Introducción</h2>
            <p>
                Esta Política de Privacidad describe cómo <strong>{{ $legalName }}</strong> ("nosotros", "nuestro"),
                con operaciones en {{ $legalCountry }}, trata la información en relación con la extensión de Google Chrome
                <strong>"Registro de Actividad Shalom"</strong> (la "extensión").
            </p>
            <p>
                La extensión es una herramienta de uso <strong>interno y corporativo</strong>: se instala en los equipos
                del personal autorizado de la empresa para dejar constancia de la actividad registrada en el sistema
                <code>sysprovincia2.shalomcontrol.com</code>. Al instalarla y utilizarla, usted acepta las prácticas
                descritas en esta política.
            </p>
        </section>

        <section>
            <h2>2. Finalidad de la Extensión</h2>
            <ul>
                <li>Dejar constancia de los datos que el propio operador introduce en el sistema de control de Shalom.</li>
                <li>Conservar un historial local cifrado de esa actividad para fines de auditoría interna.</li>
                <li>Sincronizar ese historial con {{ $legalName }} cuando el usuario inicia sesión con su cuenta corporativa.</li>
            </ul>
            <p>
                La extensión <strong>no realiza ninguna otra función</strong>: no muestra publicidad, no modifica el
                contenido de otras páginas y no rastrea la navegación del usuario.
            </p>
        </section>

        <section>
            <h2>3. Datos que Procesamos</h2>
            <p>La extensión trata únicamente los siguientes datos, y solo dentro del dominio autorizado:</p>
            <ul>
                <li>
                    <strong>Datos introducidos en el formulario de Shalom Control:</strong> número de documento
                    (DNI, Carné de Extranjería o RUC), número de orden de servicio (OS), clave de entrega y
                    número de paquetes. Estos valores son escritos por el propio operador en
                    <code>sysprovincia2.shalomcontrol.com</code>.
                </li>
                <li>
                    <strong>Datos de la cuenta corporativa del usuario:</strong> nombre y correo electrónico,
                    devueltos por {{ $legalName }} tras iniciar sesión.
                </li>
                <li>
                    <strong>Datos técnicos de la instalación:</strong> identificador único de instalación,
                    versión de la extensión, nombre y versión del navegador y del sistema operativo, y nombre del
                    dispositivo. Se usan para identificar el puesto de trabajo y diagnosticar incidencias.
                </li>
                <li><strong>Fecha y hora</strong> de cada registro y de cada sincronización.</li>
            </ul>
            <p><strong>La extensión no recopila:</strong></p>
            <ul>
                <li>Historial de navegación, pestañas abiertas ni sitios visitados.</li>
                <li>Contenido de páginas distintas al dominio declarado en el manifiesto.</li>
                <li>Datos de salud, financieros, de ubicación ni credenciales de terceros.</li>
                <li>Ningún dato con fines publicitarios, de perfilado o de análisis de comportamiento.</li>
            </ul>
        </section>

        <section>
            <h2>4. Contraseñas</h2>
            <p>
                Para iniciar sesión, la extensión envía su correo y contraseña <strong>una sola vez</strong> a la API de
                {{ $legalName }} mediante HTTPS, con el único fin de obtener un token de sincronización.
            </p>
            <p>
                <strong>La contraseña nunca se guarda</strong> en el navegador, ni en el historial local, ni en ningún
                registro de la extensión. Solo se conserva el token, que puede revocarse cerrando sesión.
            </p>
        </section>

        <section>
            <h2>5. Almacenamiento y Cifrado</h2>
            <ul>
                <li>
                    El historial se guarda localmente en su navegador mediante <code>IndexedDB</code>, y las
                    preferencias y el token mediante la API <code>chrome.storage</code>.
                </li>
                <li>
                    Cada valor registrado se cifra en el equipo con <strong>AES-256-GCM</strong>, usando una clave
                    derivada de una frase de acceso que solo conoce el usuario (PBKDF2-SHA256, 250 000 iteraciones).
                    Sin esa frase, el historial local no puede leerse.
                </li>
                <li>
                    Los datos sincronizados se almacenan en la infraestructura de {{ $legalName }}, con acceso
                    restringido al personal autorizado de la empresa.
                </li>
                <li>
                    El almacenamiento local puede borrarse en cualquier momento desde la propia extensión o
                    desinstalándola.
                </li>
            </ul>
        </section>

        <section>
            <h2>6. Uso de los Datos</h2>
            <p>La información se utiliza exclusivamente para:</p>
            <ul>
                <li><strong>Auditoría interna:</strong> dejar constancia de la actividad operativa registrada por el personal.</li>
                <li><strong>Autenticación:</strong> validar la identidad del usuario y su instalación.</li>
                <li><strong>Sincronización:</strong> consolidar el historial en {{ $legalName }}.</li>
                <li><strong>Diagnóstico y seguridad:</strong> detectar errores y accesos indebidos.</li>
            </ul>
            <p>
                Estos usos corresponden a la <strong>única finalidad declarada</strong> de la extensión. No empleamos los
                datos para ningún propósito ajeno a esa funcionalidad.
            </p>
        </section>

        <section>
            <h2>7. Compartición de Datos</h2>
            <ul>
                <li><strong>No vendemos ni transferimos datos personales a terceros.</strong></li>
                <li><strong>No utilizamos los datos con fines publicitarios</strong> ni los cedemos a corredores de datos.</li>
                <li><strong>No usamos los datos para determinar solvencia</strong> ni para concesión de créditos.</li>
                <li>
                    Solo intervienen proveedores técnicos indispensables para operar el servicio (por ejemplo,
                    alojamiento de servidores) o autoridades competentes cuando exista una obligación legal.
                </li>
            </ul>
        </section>

        <section>
            <h2>8. Seguridad</h2>
            <ul>
                <li>Toda comunicación entre la extensión y nuestros servidores viaja por <strong>HTTPS</strong>.</li>
                <li>El historial local está cifrado con AES-256-GCM antes de escribirse en disco.</li>
                <li>Los tokens se tratan como credenciales sensibles y no se muestran completos en registros ni mensajes de error.</li>
                <li>El acceso a los datos sincronizados está restringido por roles y permisos en {{ $legalName }}.</li>
            </ul>
        </section>

        <section>
            <h2>9. Retención de Datos</h2>
            <p>
                Los datos se conservan durante el tiempo necesario para cumplir la finalidad de auditoría interna,
                atender obligaciones legales y resolver incidentes de seguridad. Transcurrido ese plazo se eliminan
                o se anonimizan.
            </p>
            <p>
                El historial almacenado localmente puede ser eliminado por usted en cualquier momento desde la
                extensión o al desinstalarla.
            </p>
        </section>

        <section>
            <h2>10. Sus Derechos</h2>
            <p>Usted puede, en cualquier momento:</p>
            <ul>
                <li><strong>Acceder</strong> a los datos que tratamos sobre usted.</li>
                <li><strong>Rectificar</strong> información incorrecta.</li>
                <li><strong>Solicitar la eliminación</strong> de su información de nuestros sistemas.</li>
                <li><strong>Cerrar sesión</strong> para revocar el token de sincronización.</li>
                <li><strong>Borrar</strong> el historial local o desinstalar la extensión.</li>
            </ul>
            <p>
                Para ejercer estos derechos escríbanos a
                <!--email_off--><a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a><!--/email_off-->. Atendemos las solicitudes en un plazo
                razonable conforme a la normativa de protección de datos personales aplicable en {{ $legalCountry }}.
            </p>
        </section>

        <section>
            <h2>11. Contacto</h2>
            <p>
                Responsable del tratamiento: <strong>{{ $legalName }}</strong>.
                Correo de contacto: <!--email_off--><a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a><!--/email_off-->.
            </p>
        </section>

        <section>
            <h2>12. Cambios en la Política</h2>
            <p>
                Podemos actualizar esta política. Cualquier cambio se publicará en esta misma página junto con una
                nueva fecha de actualización. Le recomendamos revisarla periódicamente.
            </p>
        </section>

        <footer class="mt-8 text-center">
            <a href="{{ route('public.registro-actividad-shalom.support') }}" class="text-blue-400 hover:underline">Ir a Soporte</a>
            |
            <a href="{{ config('app.url') }}" class="text-blue-400 hover:underline" target="_blank" rel="noopener noreferrer">
                Volver a {{ $legalName }}
            </a>
        </footer>
    </div>
</x-layouts.public>
