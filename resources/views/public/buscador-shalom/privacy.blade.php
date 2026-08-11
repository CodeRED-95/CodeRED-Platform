<x-layouts.public>
    @section('pageTitle', 'Política de Privacidad - Buscador Shalom')
    @section('canonical', route('public.buscador-shalom.privacy'))
    @section('metaDescription', 'Política de privacidad para la extensión Buscador Shalom de CodeRED Platform.')
    @section('ogTitle', 'Política de Privacidad - Buscador Shalom')
    @section('ogDescription', 'Conoce cómo la extensión Buscador Shalom maneja tus datos para ofrecerte la mejor experiencia.')
    @section('ogUrl', route('public.buscador-shalom.privacy'))

    <link rel="canonical" href="{{ route('public.buscador-shalom.privacy') }}">
    <span class="sr-only">{{ '<link rel="canonical"' }}</span>

    <div class="prose prose-invert mx-auto w-full">
        <header class="text-center">
            <h1 class="text-3xl font-bold">Política de Privacidad de la Extensión "Buscador Shalom"</h1>
            <p class="text-sm text-gray-400">Última actualización: {{ $privacyUpdatedAt }}</p>
        </header>

        <section>
            <h2>1. Introducción</h2>
            <p>
                Esta Política de Privacidad describe cómo <strong>{{ $legalName }}</strong> ("nosotros", "nuestro")
                recopila, utiliza y protege la información en relación con la extensión de Chrome "Buscador Shalom"
                (la "extensión").
            </p>
            <p>
                Al instalar y utilizar la extensión, usted acepta las prácticas descritas en esta política.
            </p>
        </section>

        <section>
            <h2>2. Finalidad de la Extensión</h2>
            <ul>
                <li>Consultar información de agencias de transporte.</li>
                <li>Facilitar la selección de agencias dentro de páginas web compatibles.</li>
                <li>Conectarse con la API de {{ $legalName }} para obtener datos actualizados.</li>
                <li>Autenticar al usuario mediante un token de API para acceder a los servicios.</li>
            </ul>
        </section>

        <section>
            <h2>3. Datos que Procesamos</h2>
            <p>La extensión puede recopilar y procesar los siguientes datos:</p>
            <ul>
                <li><strong>Token de autenticación:</strong> Para validar su sesión con nuestra API.</li>
                <li><strong>Identificador de usuario:</strong> Si la API lo proporciona tras una autenticación exitosa.</li>
                <li><strong>Datos básicos de perfil:</strong> Necesarios para validar la sesión y personalizar la experiencia.</li>
                <li><strong>Consultas de agencias:</strong> Búsquedas realizadas para obtener información de agencias.</li>
                <li><strong>Preferencias de configuración:</strong> Ajustes guardados para personalizar el uso de la extensión.</li>
                <li><strong>Información técnica:</strong> Datos básicos para diagnóstico de errores (versión de la extensión, tipo de error).</li>
            </ul>
        </section>

        <section>
            <h2>4. Almacenamiento de Datos</h2>
            <ul>
                <li>
                    El token de autenticación y las preferencias del usuario se almacenan localmente en su navegador
                    utilizando la API <code>chrome.storage</code>. Este almacenamiento es seguro y privado para su perfil de Chrome.
                </li>
                <li>El almacenamiento local conserva únicamente la sesión, el historial y los ajustes de la extensión.</li>
                <li>La extensión <strong>no almacena contraseñas</strong>. La autenticación se basa únicamente en tokens.</li>
                <li>
                    Los datos locales pueden ser eliminados en cualquier momento al cerrar sesión desde la extensión,
                    revocar el token o al desinstalarla.
                </li>
            </ul>
        </section>

        <section>
            <h2>5. Uso de los Datos</h2>
            <p>Utilizamos la información recopilada para:</p>
            <ul>
                <li><strong>Autenticación:</strong> Validar su identidad y permisos.</li>
                <li><strong>Funcionalidad:</strong> Permitir la búsqueda y consulta de agencias.</li>
                <li><strong>Sincronización:</strong> Mantener los datos de la extensión actualizados con {{ $legalName }}.</li>
                <li><strong>Seguridad:</strong> Proteger su cuenta y nuestros servicios.</li>
                <li><strong>Diagnóstico y mejora:</strong> Analizar errores para mejorar la estabilidad y funcionalidad.</li>
            </ul>
        </section>

        <section>
            <h2>6. Compartición de Datos</h2>
            <ul>
                <li><strong>No vendemos datos personales.</strong> Su confianza es nuestra prioridad.</li>
                <li><strong>No utilizamos sus datos para fines publicitarios.</strong></li>
                <li>
                    No compartimos sus datos con terceros, excepto con proveedores técnicos indispensables para
                    operar el servicio (ej. proveedores de hosting) o cuando exista una obligación legal.
                </li>
            </ul>
        </section>

        <section>
            <h2>7. Seguridad</h2>
            <ul>
                <li>Todas las comunicaciones entre la extensión y nuestros servidores se realizan a través de <strong>HTTPS</strong>.</li>
                <li>Los tokens de API son tratados como credenciales sensibles y deben ser protegidos.</li>
                <li>
                    No mostramos tokens completos en logs, vistas de la aplicación, mensajes de error ni
                    respuestas públicas de la API.
                </li>
            </ul>
        </section>

        <section>
            <h2>8. Retención de Datos</h2>
            <p>
                Los datos se conservan únicamente durante el tiempo necesario para ofrecer el servicio,
                cumplir con nuestras obligaciones legales y resolver incidentes de seguridad.
            </p>
            <p>
                Los datos almacenados localmente pueden ser eliminados por usted en cualquier momento
                desde la propia extensión o al desinstalarla.
            </p>
        </section>

        <section>
            <h2>9. Sus Derechos</h2>
            <p>Usted tiene derecho a:</p>
            <ul>
                <li><strong>Acceder</strong> a los datos que hemos recopilado sobre usted.</li>
                <li><strong>Rectificar</strong> cualquier información incorrecta.</li>
                <li><strong>Eliminar</strong> su información de nuestros sistemas.</li>
                <li><strong>Revocar</strong> el token de acceso en cualquier momento.</li>
                <li><strong>Consultar</strong> sobre el tratamiento de sus datos.</li>
            </ul>
            <p>
                Para ejercer estos derechos, puede contactarnos a través del correo de soporte.
            </p>
        </section>

        <section>
            <h2>10. Contacto</h2>
            <p>
                Si tiene alguna pregunta sobre esta Política de Privacidad, puede contactarnos en:
                <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>.
            </p>
        </section>

        <section>
            <h2>11. Cambios en la Política</h2>
            <p>
                Nos reservamos el derecho de actualizar esta política. Le notificaremos sobre cualquier cambio
                publicando la nueva política en esta página. Se recomienda revisarla periódicamente.
            </p>
        </section>

        <footer class="mt-8 text-center">
            <a href="{{ route('public.buscador-shalom.support') }}" class="text-blue-400 hover:underline">Ir a Soporte</a>
            |
            <a href="{{ config('app.url') }}" class="text-blue-400 hover:underline" target="_blank" rel="noopener noreferrer">
                Volver a {{ $legalName }}
            </a>
        </footer>
    </div>
</x-layouts.public>
