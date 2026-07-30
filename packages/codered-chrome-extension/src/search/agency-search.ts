import { isPublicAgency, type Agency } from '../models/agency';
import { normalizeText } from '../utils/format';

export interface AgencySearchResult {
  agency: Agency;
  score: number;
}

export function searchAgencies(agencies: Agency[], query: string, limit = 20): AgencySearchResult[] {
  const normalizedQuery = normalizeText(query);
  const terms = normalizedQuery.split(' ').filter(Boolean);
  if (normalizedQuery === '') {
    return agencies.filter(isPublicAgency).slice(0, limit).map((agency) => ({ agency, score: 1 }));
  }

  return agencies
    .filter(isPublicAgency)
    .map((agency) => ({ agency, score: scoreAgency(agency, normalizedQuery, terms) }))
    .filter((result) => result.score > 0)
    .sort((a, b) => b.score - a.score || String(a.agency.name).localeCompare(b.agency.name))
    .slice(0, limit);
}

function scoreAgency(agency: Agency, query: string, terms: string[]): number {
  const code = normalizeText(agency.code);
  const name = normalizeText(agency.name);
  const oldName = normalizeText(agency.oldName);
  const shortName = normalizeText(agency.shortName);
  const location = normalizeText([agency.department, agency.province, agency.district, agency.address, agency.reference, agency.ubigeoId, agency.category, agency.sendsCategory, agency.receivesCategory].filter(Boolean).join(' '));

  if (code !== '' && code === query) return 100;
  if (name === query || shortName === query) return 90;
  if (name.startsWith(query) || shortName.startsWith(query)) return 80;
  if (oldName === query || includesAll(oldName, terms)) return 70;
  if (includesAll(name, terms) || includesAll(shortName, terms)) return 60;
  if (includesAll(location, terms)) return 40;
  return 0;
}

function includesAll(value: string, terms: string[]): boolean {
  return terms.length > 0 && terms.every((term) => value.includes(term));
}
