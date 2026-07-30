import type { Agency } from '../models/agency';

export function normalizeText(value: string | number | null | undefined): string {
  return String(value ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, ' ')
    .replace(/centro\s+de\s+operaciones/g, 'co')
    .replace(/\b(c)\s+(o)\b/g, 'co')
    .replace(/\s+/g, ' ')
    .trim();
}

export function maskToken(token: string): string {
  const trimmed = token.trim();
  if (trimmed.length <= 8) return '•'.repeat(trimmed.length);
  return `${trimmed.slice(0, 4)}••••••••••••${trimmed.slice(-4)}`;
}

export function isSafeHttpUrl(value: string | null): boolean {
  if (!value) return false;
  try {
    const url = new URL(value);
    return url.protocol === 'http:' || url.protocol === 'https:';
  } catch {
    return false;
  }
}

export function buildMapsUrl(agency: Agency): string {
  if (isSafeHttpUrl(agency.mapUrl)) return agency.mapUrl as string;
  if (agency.latitude !== null && agency.longitude !== null) {
    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(`${agency.latitude},${agency.longitude}`)}`;
  }
  const query = [agency.name, agency.address, agency.department, agency.province, agency.district].filter(Boolean).join(' ');
  return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(query)}`;
}
