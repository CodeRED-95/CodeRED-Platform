package lat.codered.anime.tv.data

import android.content.Context
import android.net.nsd.NsdManager
import android.net.nsd.NsdServiceInfo
import android.util.Log
import lat.codered.anime.tv.domain.Anime
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.net.InetSocketAddress
import java.net.ServerSocket
import java.net.Socket
import kotlin.concurrent.thread

/**
 * Envio de reproduccion entre el movil y el Android TV por la red local.
 *
 * No es Google Cast: la TV se anuncia por NSD y expone un servidor minimo, y el
 * movil solo le manda que anime y capitulo abrir. La TV resuelve el stream con
 * su propio cliente, que es lo unico que sabe mandar las cabeceras Referer y
 * Origin que exige el proveedor; un receptor de Chromecast generico no podria.
 */
object LocalCast {
    const val SERVICE_TYPE = "_coderedtv._tcp."
    const val DEFAULT_PORT = 8765
    private const val TAG = "LocalCast"

    /** Un Android TV encontrado en la red. */
    data class Receiver(
        val name: String,
        val host: String,
        val port: Int,
    )

    /** Orden de reproduccion que viaja del movil a la TV. */
    data class PlayRequest(
        val animeId: String,
        val slug: String,
        val title: String,
        val posterUrl: String?,
        val episodeNumber: Int,
    ) {
        fun toJson(): String = JSONObject()
            .put("anime_id", animeId)
            .put("slug", slug)
            .put("title", title)
            .put("poster_url", posterUrl)
            .put("episode", episodeNumber)
            .toString()

        fun toAnime(): Anime = Anime(
            id = animeId,
            slug = slug,
            title = title,
            posterUrl = posterUrl,
        )

        companion object {
            fun fromJson(raw: String): PlayRequest? = runCatching {
                val json = JSONObject(raw)
                val slug = json.optString("slug").takeIf { it.isNotBlank() } ?: return@runCatching null
                val episode = json.optInt("episode").takeIf { it > 0 } ?: return@runCatching null
                PlayRequest(
                    animeId = json.optString("anime_id").takeIf { it.isNotBlank() } ?: "jkanime:$slug",
                    slug = slug,
                    title = json.optString("title").takeIf { it.isNotBlank() } ?: slug,
                    posterUrl = json.optString("poster_url").takeIf { it.isNotBlank() },
                    episodeNumber = episode,
                )
            }.getOrNull()
        }
    }
}

/**
 * Lado television: escucha ordenes y se anuncia en la red.
 *
 * El servidor es deliberadamente diminuto (solo /ping y /play) porque acepta
 * conexiones de la red local sin autenticar: cuanto menos exponga, mejor.
 */
class LocalCastServer(
    private val deviceName: String,
    private val onPlay: (LocalCast.PlayRequest) -> Unit,
) {
    private var serverSocket: ServerSocket? = null
    private var nsdManager: NsdManager? = null
    private var registrationListener: NsdManager.RegistrationListener? = null
    @Volatile
    private var running = false

    fun start(context: Context) {
        if (running) return
        running = true

        thread(name = "codered-cast-server", isDaemon = true) {
            runCatching {
                val socket = ServerSocket(LocalCast.DEFAULT_PORT)
                serverSocket = socket
                announce(context, socket.localPort)
                while (running) {
                    val client = runCatching { socket.accept() }.getOrNull() ?: break
                    handle(client)
                }
            }.onFailure { Log.w("LocalCast", "servidor detenido: ${it.message}") }
        }
    }

    fun stop() {
        running = false
        runCatching { serverSocket?.close() }
        serverSocket = null
        registrationListener?.let { listener ->
            runCatching { nsdManager?.unregisterService(listener) }
        }
        registrationListener = null
        nsdManager = null
    }

    private fun announce(context: Context, port: Int) {
        val manager = context.getSystemService(Context.NSD_SERVICE) as? NsdManager ?: return
        val info = NsdServiceInfo().apply {
            serviceName = deviceName
            serviceType = LocalCast.SERVICE_TYPE
            setPort(port)
        }
        val listener = object : NsdManager.RegistrationListener {
            override fun onServiceRegistered(info: NsdServiceInfo) = Unit
            override fun onRegistrationFailed(info: NsdServiceInfo, errorCode: Int) {
                Log.w("LocalCast", "no se pudo anunciar el receptor: $errorCode")
            }

            override fun onServiceUnregistered(info: NsdServiceInfo) = Unit
            override fun onUnregistrationFailed(info: NsdServiceInfo, errorCode: Int) = Unit
        }
        registrationListener = listener
        nsdManager = manager
        runCatching { manager.registerService(info, NsdManager.PROTOCOL_DNS_SD, listener) }
    }

    private fun handle(client: Socket) {
        client.use { socket ->
            runCatching {
                val reader = BufferedReader(InputStreamReader(socket.getInputStream()))
                val requestLine = reader.readLine().orEmpty()
                var contentLength = 0
                while (true) {
                    val line = reader.readLine().orEmpty()
                    if (line.isBlank()) break
                    if (line.startsWith("Content-Length:", ignoreCase = true)) {
                        contentLength = line.substringAfter(':').trim().toIntOrNull() ?: 0
                    }
                }

                val body = if (contentLength > 0) {
                    val buffer = CharArray(contentLength)
                    var read = 0
                    while (read < contentLength) {
                        val count = reader.read(buffer, read, contentLength - read)
                        if (count <= 0) break
                        read += count
                    }
                    String(buffer, 0, read)
                } else {
                    ""
                }

                val response = when {
                    requestLine.startsWith("GET /ping") ->
                        JSONObject().put("app", "CodeRED Anime TV").put("device", deviceName).toString()

                    requestLine.startsWith("POST /play") -> {
                        val request = LocalCast.PlayRequest.fromJson(body)
                        if (request == null) {
                            JSONObject().put("ok", false).put("error", "peticion invalida").toString()
                        } else {
                            onPlay(request)
                            JSONObject().put("ok", true).toString()
                        }
                    }

                    else -> JSONObject().put("ok", false).put("error", "ruta desconocida").toString()
                }

                socket.getOutputStream().apply {
                    write(
                        buildString {
                            append("HTTP/1.1 200 OK\r\n")
                            append("Content-Type: application/json; charset=utf-8\r\n")
                            append("Content-Length: ${response.toByteArray().size}\r\n")
                            append("Connection: close\r\n\r\n")
                            append(response)
                        }.toByteArray(),
                    )
                    flush()
                }
            }
        }
    }
}

/** Lado movil: descubre televisores y les manda ordenes. */
class LocalCastClient(private val context: Context) {
    private val tag = "LocalCast"

    private var nsdManager: NsdManager? = null
    private var discoveryListener: NsdManager.DiscoveryListener? = null

    fun startDiscovery(onFound: (LocalCast.Receiver) -> Unit, onLost: (String) -> Unit) {
        if (discoveryListener != null) return
        val manager = context.getSystemService(Context.NSD_SERVICE) as? NsdManager ?: return

        val listener = object : NsdManager.DiscoveryListener {
            override fun onDiscoveryStarted(serviceType: String) = Unit

            override fun onServiceFound(info: NsdServiceInfo) {
                if (info.serviceType?.contains("coderedtv") != true) return
                // resolveService esta obsoleto en API 34 pero es la unica via
                // disponible en minSdk 23.
                @Suppress("DEPRECATION")
                manager.resolveService(
                    info,
                    object : NsdManager.ResolveListener {
                        override fun onResolveFailed(info: NsdServiceInfo, errorCode: Int) = Unit

                        override fun onServiceResolved(resolved: NsdServiceInfo) {
                            val host = resolved.host?.hostAddress ?: return
                            Log.i(tag, "receptor resuelto: ${resolved.serviceName} -> $host:${resolved.port}")
                            onFound(
                                LocalCast.Receiver(
                                    name = resolved.serviceName ?: "Android TV",
                                    host = host,
                                    port = resolved.port,
                                ),
                            )
                        }
                    },
                )
            }

            override fun onServiceLost(info: NsdServiceInfo) {
                info.serviceName?.let(onLost)
            }

            override fun onDiscoveryStopped(serviceType: String) = Unit
            override fun onStartDiscoveryFailed(serviceType: String, errorCode: Int) = Unit
            override fun onStopDiscoveryFailed(serviceType: String, errorCode: Int) = Unit
        }

        discoveryListener = listener
        nsdManager = manager
        runCatching { manager.discoverServices(LocalCast.SERVICE_TYPE, NsdManager.PROTOCOL_DNS_SD, listener) }
    }

    fun stopDiscovery() {
        discoveryListener?.let { listener ->
            runCatching { nsdManager?.stopServiceDiscovery(listener) }
        }
        discoveryListener = null
        nsdManager = null
    }

    /**
     * Devuelve true si la television acepto la orden.
     *
     * Se escribe la peticion sobre un socket en vez de usar OkHttp a proposito:
     * la politica de seguridad de red de Android prohibe el trafico en claro y
     * el receptor local habla HTTP plano. Abrir cleartext en el manifiesto lo
     * permitiria tambien contra internet, que es justo lo que no queremos.
     */
    fun send(receiver: LocalCast.Receiver, request: LocalCast.PlayRequest): Boolean {
        val crlf = "\u000D\u000A"
        val payload = request.toJson().toByteArray()
        val head = buildString {
            append("POST /play HTTP/1.1").append(crlf)
            append("Host: ${authority(receiver.host)}:${receiver.port}").append(crlf)
            append("Content-Type: application/json; charset=utf-8").append(crlf)
            append("Content-Length: ${payload.size}").append(crlf)
            append("Connection: close").append(crlf).append(crlf)
        }.toByteArray()

        return runCatching {
            Socket().use { socket ->
                socket.connect(InetSocketAddress(receiver.host.substringBefore('%'), receiver.port), 3_000)
                socket.soTimeout = 5_000
                socket.getOutputStream().apply {
                    write(head)
                    write(payload)
                    flush()
                }
                socket.getInputStream().bufferedReader().readText().contains("\"ok\":true")
            }
        }.onFailure { Log.w(tag, "envio fallido a ${receiver.host}: $it") }.getOrDefault(false)
    }

    private fun authority(host: String): String {
        val clean = host.substringBefore('%')
        return if (clean.contains(':')) "[$clean]" else clean
    }
}
