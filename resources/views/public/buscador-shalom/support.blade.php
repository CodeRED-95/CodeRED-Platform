<x-layouts.public>
    @section('pageTitle', 'Soporte - Buscador Shalom')
    @section('canonical', route('public.buscador-shalom.support'))
    @section('metaDescription', 'Página de soporte para la extensión Buscador Shalom. Encuentra ayuda, guías y soluciones a problemas comunes.')
    @section('ogTitle', 'Soporte - Buscador Shalom')
    @section('ogDescription', 'Encuentra ayuda y soluciones a problemas comunes con la extensión Buscador Shalom.')
    @section('ogUrl', route('public.buscador-shalom.support'))

    <div class="prose prose-invert mx-auto w-full">
        <header class="text-center">
            <h1 class="text-3xl font-bold">Página de Soporte de "Buscador Shalom"</h1>
            <p>Guía de uso, solución de problemas y contacto.</p>
        </header>

        <section>
            <h2>1. Descripción General</h2>
            <p>
                La extensión <strong>Buscador Shalom</strong> para Google Chrome le permite buscar y seleccionar agencias
                de transporte directamente desde su navegador, integrándose con CodeRED Platform para ofrecer datos actualizados.
            </p>
        </section>

        <section>
            <h2>2. Instalación</h2>
            <ol>
                <li>Instale la extensión desde la <a href="https://chrome.google.com/webstore" target="_blank" rel="noopener noreferrer">Chrome Web Store</a>.</li>
                <li>Una vez instalada, asegúrese de que esté habilitada en la gestión de extensiones de Chrome (<code>chrome://extensions</code>).</li>
                <li>Para un acceso rápido, puede fijar la extensión a la barra de herramientas de Chrome.</li>
            </ol>
        </section>

        <section>
            <h2>3. Autenticación</h2>
            <ul>
                <li><strong>Solicitar Token:</strong> Para usar la extensión, necesita un token de API. Puede solicitarlo desde la propia extensión o a través de CodeRED Platform.</li>
                <li><strong>Verificar Sesión:</strong> Cuando el token está activo, la extensión muestra un indicador de estado conectado.</li>
                <li><strong>Cerrar Sesión:</strong> Puede cerrar su sesión en cualquier momento desde el menú de la extensión. Esto eliminará el token almacenado localmente.</li>
            </ul>
        </section>

        <section>
            <h2>4. Uso Básico</h2>
            <ul>
                <li><strong>Buscar Agencia:</strong> Utilice el campo de búsqueda para encontrar agencias por nombre, departamento, provincia o distrito.</li>
                <li><strong>Consultar Detalles:</strong> Acceda a la dirección, teléfono y otros canales de contacto de cada agencia.</li>
                <li><strong>Seleccionar Agencia:</strong> En páginas web compatibles, la extensión puede autocompletar formularios con la información de la agencia seleccionada.</li>
            </ul>
        </section>

        <section>
            <h2>5. Problemas Frecuentes y Soluciones</h2>
            <div class="space-y-4">
                <div>
                    <h3 class="font-semibold">Token inválido o vencido</h3>
                    <p><strong>Solución:</strong> Cierre sesión y vuelva a ingresar con un token nuevo y válido. Asegúrese de que el token no haya expirado.</p>
                </div>
                <div>
                    <h3 class="font-semibold">No se muestran agencias</h3>
                    <p><strong>Solución:</strong> Verifique su conexión a Internet y asegúrese de que su token sea correcto. Intente recargar la página o reiniciar el navegador.</p>
                </div>
                <div>
                    <h3 class="font-semibold">Error de conexión con CodeRED Platform</h3>
                    <p><strong>Solución:</strong> Es posible que nuestros servidores no estén disponibles temporalmente. Por favor, inténtelo de nuevo más tarde.</p>
                </div>
                <div>
                    <h3 class="font-semibold">La extensión no detecta el formulario de destino</h3>
                    <p><strong>Solución:</strong> Asegúrese de estar en una página web compatible. Si el problema persiste, infórmenos la URL para que podamos investigarlo.</p>
                </div>
                <div>
                    <h3 class="font-semibold">La extensión está deshabilitada</h3>
                    <p><strong>Solución:</strong> Vaya a <code>chrome://extensions</code> y verifique que el interruptor de la extensión "Buscador Shalom" esté activado.</p>
                </div>
                 <div>
                    <h3 class="font-semibold">La página requiere recargarse</h3>
                    <p><strong>Solución:</strong> Después de instalar o actualizar una extensión, a menudo es necesario recargar las pestañas abiertas para que funcione correctamente.</p>
                </div>
            </div>
        </section>

        <section>
            <h2>6. Contacto</h2>
            <p>
                Si los problemas persisten, no dude en contactarnos.
            </p>
            <ul>
                <li><strong>Correo de Soporte:</strong> <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a></li>
                <li><strong>Sitio Principal:</strong> <a href="https://platform.codered.host" target="_blank" rel="noopener noreferrer">platform.codered.host</a></li>
                <li><strong>Política de Privacidad:</strong> <a href="{{ route('public.buscador-shalom.privacy') }}">Leer aquí</a></li>
            </ul>
        </section>

        <section>
            <h2>7. Cómo Reportar un Problema</h2>
            <p>Para ayudarnos a resolver su problema rápidamente, por favor incluya la siguiente información:</p>
            <ul>
                <li>Versión de Google Chrome.</li>
                <li>Versión de la extensión "Buscador Shalom".</li>
                <li>La URL exacta donde ocurrió el error.</li>
                <li>Pasos detallados para reproducir el problema.</li>
                <li>Una captura de pantalla (si es posible), ocultando cualquier dato sensible.</li>
            </ul>
            <div class="border-l-4 border-red-500 bg-red-900/20 p-4">
                <h4 class="font-bold">Advertencia de Seguridad</h4>
                <p>
                    <strong>Nunca</strong> comparta sus tokens de API completos por correo, chat o en capturas de pantalla.
                    Trátelos como si fueran contraseñas.
                </p>
            </div>
        </section>

        <footer class="mt-8 text-center">
            <a href="{{ route('public.buscador-shalom.privacy') }}" class="text-blue-400 hover:underline">Ir a Política de Privacidad</a>
            |
            <a href="https://platform.codered.host" class="text-blue-400 hover:underline" target="_blank" rel="noopener noreferrer">
                Volver a CodeRED Platform
            </a>
        </footer>
    </div>
</x-layouts.public>
