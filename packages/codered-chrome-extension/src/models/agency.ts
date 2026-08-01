export type AgencyStatus = 'active' | 'moved' | 'temporarily_closed' | 'inactive' | 'under_review' | 'unknown';

export interface Agency {
  id: string | number | null;
  externalId: string | number | null;
  code: string | null;
  name: string;
  oldName: string | null;
  shortName: string | null;
  slug: string | null;
  ubigeoId: string | number | null;
  department: string | null;
  province: string | null;
  district: string | null;
  address: string | null;
  reference: string | null;
  phone: string | null;
  secondaryPhone: string | null;
  email: string | null;
  scheduleGeneral: string | null;
  scheduleSunday: string | null;
  latitude: number | null;
  longitude: number | null;
  mapUrl: string | null;
  status: AgencyStatus;
  statusLabel: string;
  category: string | null;
  sendsCategory: string | null;
  receivesCategory: string | null;
  isOperationsCenter: boolean;
  terrestrialText: string | null;
  airText: string | null;
  hasMoved: boolean;
  movedToAgencyId: string | number | null;
  movedToAgencyCode: string | null;
  movedToAgencyName: string | null;
  movedToAddress: string | null;
  observations: string | null;
  updatedAt: string | null;
}

type RawAgency = Record<string, unknown>;

export function adaptAgency(raw: RawAgency): Agency {
  const schedule = asRecord(raw.schedule);
  const classification = asRecord(raw.classification);
  const status = normalizeStatus(readString(raw, 'status') ?? readString(raw, 'estado'));
  const hasMoved = readBoolean(raw, 'has_moved') || status === 'moved';

  return {
    id: readValue(raw, 'id') ?? readValue(raw, 'external_id') ?? null,
    externalId: readValue(raw, 'external_id') ?? readValue(raw, 'id') ?? null,
    code: readString(raw, 'code') ?? readString(raw, 'codigo'),
    name: readString(raw, 'name') ?? readString(raw, 'agencia') ?? 'Agencia sin nombre',
    oldName: readString(raw, 'old_name') ?? readString(raw, 'agencia_anterior'),
    shortName: readString(raw, 'short_name'),
    slug: readString(raw, 'slug'),
    ubigeoId: readValue(raw, 'ubigeo_id') ?? null,
    department: readString(raw, 'department') ?? readString(raw, 'departamento'),
    province: readString(raw, 'province') ?? readString(raw, 'provincia'),
    district: readString(raw, 'district') ?? readString(raw, 'distrito'),
    address: readString(raw, 'address') ?? readString(raw, 'direccion'),
    reference: readString(raw, 'reference') ?? readString(raw, 'referencia'),
    phone: readString(raw, 'phone') ?? readString(raw, 'telefono'),
    secondaryPhone: readString(raw, 'secondary_phone'),
    email: readString(raw, 'email'),
    scheduleGeneral: readString(raw, 'schedule_general') ?? readString(schedule, 'general') ?? readString(raw, 'schedule'),
    scheduleSunday: readString(raw, 'schedule_sunday') ?? readString(schedule, 'sunday'),
    latitude: readNumber(raw, 'latitude'),
    longitude: readNumber(raw, 'longitude'),
    mapUrl: readString(raw, 'map_url') ?? readString(raw, 'link_mapa'),
    status,
    statusLabel: statusLabel(status),
    category: readString(raw, 'classification_category') ?? readString(raw, 'category') ?? readString(raw, 'tamano') ?? readString(classification, 'category') ?? readString(classification, 'tamano'),
    sendsCategory: readString(raw, 'classification_sends_category') ?? readString(classification, 'sends_category'),
    receivesCategory: readString(raw, 'classification_receives_category') ?? readString(classification, 'receives_category'),
    isOperationsCenter: readBoolean(raw, 'is_operations_center') || readBoolean(raw, 'centro_operaciones') || readBoolean(raw, 'co'),
    terrestrialText: readString(raw, 'texto_chosen_terrestre') ?? readString(raw, 'chosen_terrestre') ?? readString(raw, 'texto_chosen'),
    airText: readString(raw, 'texto_chosen_aereo') ?? readString(raw, 'chosen_aereo'),
    hasMoved,
    movedToAgencyId: readValue(raw, 'moved_to_agency_id') ?? null,
    movedToAgencyCode: readString(raw, 'moved_to_agency_code'),
    movedToAgencyName: readString(raw, 'moved_to_agency_name'),
    movedToAddress: readString(raw, 'moved_to_address'),
    observations: readString(raw, 'observations'),
    updatedAt: readString(raw, 'updated_at'),
  };
}

export function isPublicAgency(agency: Agency): boolean {
  return agency.status === 'active' || agency.status === 'moved' || agency.status === 'temporarily_closed';
}

export function statusNotice(agency: Agency): { tone: 'warning' | 'danger'; message: string; details: string[] } | null {
  if (agency.status === 'moved' || agency.hasMoved) {
    return {
      tone: 'warning',
      message: 'Esta agencia fue trasladada',
      details: [agency.movedToAgencyName ? `Destino: ${agency.movedToAgencyName}` : null, agency.movedToAddress ? `Nueva direccion: ${agency.movedToAddress}` : null].filter((value): value is string => value !== null),
    };
  }

  if (agency.status === 'temporarily_closed') {
    return {
      tone: 'danger',
      message: 'Esta agencia se encuentra cerrada temporalmente',
      details: agency.observations ? [agency.observations] : [],
    };
  }

  return null;
}

function normalizeStatus(value: string | null): AgencyStatus {
  const normalized = value?.trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/\s+/g, '_') ?? '';
  if (['active', 'activa'].includes(normalized)) return 'active';
  if (['moved', 'trasladada', 'trasladado'].includes(normalized)) return 'moved';
  if (['temporarily_closed', 'cerrada_temporalmente', 'cerrado_temporalmente'].includes(normalized)) return 'temporarily_closed';
  if (['inactive', 'inactiva', 'inactivo'].includes(normalized)) return 'inactive';
  if (['under_review', 'en_revision'].includes(normalized)) return 'under_review';
  return 'unknown';
}

function statusLabel(status: AgencyStatus): string {
  return { active: 'Activa', moved: 'Trasladada', temporarily_closed: 'Cerrada temporalmente', inactive: 'Inactiva', under_review: 'En revision', unknown: 'Desconocido' }[status];
}

function asRecord(value: unknown): RawAgency {
  return value !== null && typeof value === 'object' && !Array.isArray(value) ? (value as RawAgency) : {};
}

function readValue(source: RawAgency, key: string): string | number | null {
  const value = source[key];
  return typeof value === 'string' || typeof value === 'number' ? value : null;
}

function readString(source: RawAgency, key: string): string | null {
  const value = source[key];
  if (typeof value !== 'string' && typeof value !== 'number') return null;
  const text = String(value).trim();
  return text === '' ? null : text;
}

function readNumber(source: RawAgency, key: string): number | null {
  const value = source[key];
  const number = typeof value === 'number' ? value : typeof value === 'string' ? Number(value) : Number.NaN;
  return Number.isFinite(number) ? number : null;
}

function readBoolean(source: RawAgency, key: string): boolean {
  const value = source[key];
  return value === true || value === 1 || value === '1' || value === 'true';
}
