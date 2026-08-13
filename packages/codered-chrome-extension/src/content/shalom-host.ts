/**
 * Alcance de inyección del Buscador Shalom Control.
 *
 * La extensión SOLO debe ejecutarse y mostrar su interfaz en tres rutas
 * concretas de cualquier subdominio de shalomcontrol.com:
 *
 *   https://<sub>.shalomcontrol.com/listaordenservicio
 *   https://<sub>.shalomcontrol.com/ordenservicio/listar
 *   https://<sub>.shalomcontrol.com/service-order/
 *
 * `content_scripts.matches` del manifest ya restringe la inyección, pero NO es
 * la única protección: estas funciones se evalúan también en runtime, porque
 * los patrones del manifest son de grano grueso y una navegación SPA puede
 * cambiar la ruta sin recargar el content script.
 */
const SUPPORTED_DOMAIN = 'shalomcontrol.com';

/** Rutas exactas donde la extensión puede activarse (barra final opcional). */
export const SUPPORTED_PATHS = ['/listaordenservicio', '/ordenservicio/listar', '/service-order'] as const;

/**
 * `true` solo si el hostname es un subdominio de shalomcontrol.com, es decir
 * si termina en `.shalomcontrol.com`. El dominio desnudo `shalomcontrol.com`
 * NO se considera soportado.
 */
export function isSupportedShalomHost(hostname: string | null | undefined, allowedDomains?: string[] | null): boolean {
  const normalized = normalizeHostname(hostname);
  if (!normalized) return false;
  if (!normalized.endsWith(`.${SUPPORTED_DOMAIN}`)) return false;
  const restrictions = (allowedDomains ?? []).map(normalizeHostname).filter(Boolean);
  if (restrictions.length === 0) return true;
  return restrictions.some((domain) => isHostnameOrSubdomain(normalized, domain));
}

/**
 * `true` solo si la ruta es exactamente una de SUPPORTED_PATHS, admitiendo una
 * barra final opcional. Rechaza rutas anidadas (`/ordenservicio/listar/otra`) y
 * prefijos incompletos (`/ordenservicio`).
 */
export function isSupportedShalomPath(pathname: string | null | undefined): boolean {
  const normalized = normalizePathname(pathname);
  if (!normalized) return false;
  return SUPPORTED_PATHS.some((path) => normalized === path);
}

/** Puerta única de entrada: host permitido Y ruta permitida. */
export function isSupportedShalomLocation(
  hostname: string | null | undefined,
  pathname: string | null | undefined,
  allowedDomains?: string[] | null,
): boolean {
  return isSupportedShalomHost(hostname, allowedDomains) && isSupportedShalomPath(pathname);
}

export function isNeutralShalomSearchPath(pathname: string | null | undefined): boolean {
  return ['/listaordenservicio', '/service-order'].includes(normalizePathname(pathname));
}

export type ShalomPageMode = 'interactive' | 'neutral';

export interface ShalomPageCapabilities {
  mode: ShalomPageMode;
  search: boolean;
  neutralChannel: boolean;
  agencySelection: boolean;
  channelDetection: boolean;
}

export function getShalomPageCapabilities(pathname: string | null | undefined): ShalomPageCapabilities {
  if (isNeutralShalomSearchPath(pathname)) {
    return {
      mode: 'neutral',
      search: true,
      neutralChannel: true,
      agencySelection: false,
      channelDetection: false,
    };
  }

  return {
    mode: 'interactive',
    search: true,
    neutralChannel: false,
    agencySelection: true,
    channelDetection: true,
  };
}

export function hostnameMatchesAllowedDomain(hostname: string, allowedDomain: string): boolean {
  return isHostnameOrSubdomain(hostname, allowedDomain);
}

export function isHostnameOrSubdomain(hostname: string, domain: string): boolean {
  const host = normalizeHostname(hostname);
  const normalizedDomain = normalizeHostname(domain);
  return host === normalizedDomain || host.endsWith(`.${normalizedDomain}`);
}

function normalizeHostname(hostname: string | null | undefined): string {
  return String(hostname ?? '').trim().toLowerCase().replace(/\.$/, '');
}

/**
 * `location.pathname` nunca incluye query ni hash, pero se recortan igualmente
 * por si se invoca con una URL completa. La comparación es insensible a
 * mayúsculas porque solo afecta a las dos rutas ya autorizadas.
 */
function normalizePathname(pathname: string | null | undefined): string {
  const raw = String(pathname ?? '').trim();
  if (!raw) return '';
  const withoutFragment = raw.split('#')[0].split('?')[0].toLowerCase();
  if (!withoutFragment.startsWith('/')) return '';
  // Barra final opcional: `/listaordenservicio/` equivale a `/listaordenservicio`.
  return withoutFragment.length > 1 ? withoutFragment.replace(/\/+$/, '') : withoutFragment;
}
