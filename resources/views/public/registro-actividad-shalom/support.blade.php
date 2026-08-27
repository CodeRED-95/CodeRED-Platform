<x-layouts.public>
    @section('pageTitle', 'Soporte - Registro de Actividad Shalom')
    @section('canonical', route('public.registro-actividad-shalom.support'))
    @section('metaDescription', 'Página de soporte de la extensión Registro de Actividad Shalom: guía de uso, problemas frecuentes y contacto.')
    @section('ogTitle', 'Soporte - Registro de Actividad Shalom')
    @section('ogDescription', 'Ayuda y solución de problemas de la extensión Registro de Actividad Shalom.')
    @section('ogUrl', route('public.registro-actividad-shalom.support'))

    <link rel="canonical" href="{{ route('public.registro-actividad-shalom.support') }}">
    <span class="sr-only">{{ '<link rel="canonical"' }}</span>

    <div class="prose prose-invert mx-auto w-full">
        <header class="text-center">
            <h1 class="text-3xl font-bold">Página de Soporte de "Registro de Actividad Shalom"</h1>
            <p>Guía de uso, solución de problemas y contacto.</p>
        </header>

        <section>
            <h2>1. Descripción General</h2>
            <p>
                <strong>Registro de Actividad Shalom</strong> es una extensión de uso interno que deja constancia de la
                actividad que el personal autorizado registra en <code>sysprovincia2.shalomcontrol.com</code> y la
                sincroniza con {{ $legalName }}.
            </p>
        </section>

        <section>
            <h2>2. Primeros Pasos</h2>
            <ol>
                <li>Instale la extensión y ábrala desde el icono de la barra de herramientas.</li>
                <li>Inicie sesión con su cuenta corporativa de {{ $legalName }} (correo y contraseña).</li>
                <li>Defina la frase de acceso que cifrará el historial local. Sin ella no podrá leer los registros.</li>
                <li>Trabaje normalmente en Shalom Control: la extensión registra la actividad en segundo plano.</li>
                <li>Use <strong>Sincronizar</strong> para enviar los registros pendientes a la plataforma.</li>
            </ol>
        </section>

        <section>
            <h2>3. Problemas Frecuentes</h2>

            <h3>Token inválido o vencido</h3>
            <p>
                Si aparece un aviso de sesión caducada, cierre sesión desde la extensión y vuelva a iniciarla.
                Se generará un token de sincronización nuevo.
            </p>

            <h3>No se registran datos</h3>
            <p>
                Verifique que está en <code>sysprovincia2.shalomcontrol.com</code>, que la extensión está habilitada
                y que ha desbloqueado el historial con su frase de acceso. Recargue la pestaña después de comprobarlo.
            </p>

            <h3>Los registros no se sincronizan</h3>
            <p>
                Compruebe su conexión y el acceso a <code>https://platform.codered.lat</code>. Los registros pendientes
                se conservan localmente y se envían en el siguiente intento de sincronización.
            </p>

            <h3>Olvidé mi frase de acceso</h3>
            <p>
                Por diseño, la frase no se almacena en ningún sitio y no puede recuperarse. El historial local cifrado
                con esa frase deja de ser legible; deberá definir una nueva y continuar desde ahí.
            </p>
        </section>

        <section>
            <h2>4. Recomendaciones de Seguridad</h2>
            <ul>
                <li>Nunca comparta sus tokens de API completos ni su frase de acceso.</li>
                <li>Use la extensión únicamente en equipos corporativos autorizados.</li>
                <li>Cierre sesión al dejar de usar el equipo o antes de cederlo a otra persona.</li>
                <li>Mantenga la extensión actualizada a la última versión publicada.</li>
            </ul>
        </section>

        <section>
            <h2>5. Contacto</h2>
            <p>
                ¿Necesita ayuda? Escríbanos a
                <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a> indicando la versión de la extensión y una
                descripción del problema.
            </p>
        </section>

        <footer class="mt-8 text-center">
            <a href="{{ route('public.registro-actividad-shalom.privacy') }}" class="text-blue-400 hover:underline">Política de Privacidad</a>
            |
            <a href="{{ config('app.url') }}" class="text-blue-400 hover:underline" target="_blank" rel="noopener noreferrer">
                Volver a {{ $legalName }}
            </a>
        </footer>
    </div>
</x-layouts.public>
