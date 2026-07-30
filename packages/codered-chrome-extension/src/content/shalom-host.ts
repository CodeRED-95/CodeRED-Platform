const SUPPORTED_DOMAINS = ['shalomcontrol.com', 'shalom.pe'] as const;

export function isSupportedShalomHost(hostname: string | null | undefined, allowedDomains?: string[] | null): boolean {
  const normalized = normalizeHostname(hostname);
  if (!normalized) return false;
  const supported = SUPPORTED_DOMAINS.some((domain) => isHostnameOrSubdomain(normalized, domain));
  if (!supported) return false;
  const restrictions = (allowedDomains ?? []).map(normalizeHostname).filter(Boolean);
  if (restrictions.length === 0) return true;
  return restrictions.some((domain) => isHostnameOrSubdomain(normalized, domain));
}

export function isHostnameOrSubdomain(hostname: string, domain: string): boolean {
  const host = normalizeHostname(hostname);
  const normalizedDomain = normalizeHostname(domain);
  return host === normalizedDomain || host.endsWith(`.${normalizedDomain}`);
}

function normalizeHostname(hostname: string | null | undefined): string {
  return String(hostname ?? '').trim().toLowerCase().replace(/\.$/, '');
}
