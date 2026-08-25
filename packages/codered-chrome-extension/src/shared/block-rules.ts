/**
 * Motor de bloqueo horario configurado desde CodeRED Platform.
 *
 * Hasta la version 2.3.15 el horario estaba escrito a fuego en `lima-time.ts`
 * (08:00-20:05, un unico destino). Ahora la Plataforma publica las reglas en
 * `GET /api/v1/extension/chrome/block-rules` y este modulo las evalua: que URL
 * afecta cada regla, si el instante actual queda bloqueado y cuando cambia el
 * estado. Si no hay reglas sincronizadas se usa DEFAULT_BLOCK_RULE_SET, que
 * reproduce exactamente el comportamiento anterior.
 */

export type WindowMode = 'allowed' | 'blocked';

export interface BlockWindow {
  dayOfWeek: number;
  start: string;
  end: string;
}

export interface BlockRule {
  id: number;
  label: string;
  hostPattern: string;
  pathPattern: string;
  windowMode: WindowMode;
  timezone: string;
  windows: BlockWindow[];
}

export interface BlockRuleSet {
  version: string;
  generatedAt: string | null;
  rules: BlockRule[];
}

export interface RuleEvaluation {
  rule: BlockRule | null;
  matched: boolean;
  blockedBySchedule: boolean;
  nextChangeAt: Date | null;
  remainingMs: number;
  remainingLabel: string;
  periodId: string | null;
  scheduleLabel: string;
}

export const DEFAULT_BLOCK_RULE: BlockRule = {
  id: 0,
  label: 'Service Order',
  hostPattern: 'sysnewos.shalomcontrol.com',
  pathPattern: '/service-order',
  windowMode: 'allowed',
  timezone: 'America/Lima',
  windows: [0, 1, 2, 3, 4, 5, 6].map((dayOfWeek) => ({ dayOfWeek, start: '08:00', end: '20:05' })),
};

export const DEFAULT_BLOCK_RULE_SET: BlockRuleSet = {
  version: 'default',
  generatedAt: null,
  rules: [DEFAULT_BLOCK_RULE],
};

const MINUTES_PER_DAY = 24 * 60;
const LOOKAHEAD_DAYS = 8;

/** Normaliza la respuesta de la API; devuelve null si no es utilizable. */
export function parseBlockRuleSet(payload: unknown): BlockRuleSet | null {
  const root = asRecord(payload);
  if (!root) return null;
  const data = asRecord(root.data) ?? root;
  const rawRules = data.rules;
  if (!Array.isArray(rawRules)) return null;

  const rules = rawRules.map(parseBlockRule).filter((rule): rule is BlockRule => rule !== null);

  return {
    version: typeof data.version === 'string' ? data.version : '',
    generatedAt: typeof data.generated_at === 'string' ? data.generated_at : null,
    rules,
  };
}

function parseBlockRule(raw: unknown): BlockRule | null {
  const rule = asRecord(raw);
  if (!rule) return null;

  const hostPattern = typeof rule.host_pattern === 'string' ? rule.host_pattern.trim().toLowerCase() : '';
  if (hostPattern === '') return null;

  const windows = Array.isArray(rule.windows)
    ? rule.windows
        .map((value) => {
          const window = asRecord(value);
          if (!window) return null;
          const dayOfWeek = Number(window.day_of_week);
          const start = normalizeTime(window.start_time);
          const end = normalizeTime(window.end_time);
          if (!Number.isInteger(dayOfWeek) || dayOfWeek < 0 || dayOfWeek > 6 || start === null || end === null) return null;
          return { dayOfWeek, start, end } satisfies BlockWindow;
        })
        .filter((window): window is BlockWindow => window !== null)
    : [];

  return {
    id: Number(rule.id) || 0,
    label: typeof rule.label === 'string' && rule.label.trim() !== '' ? rule.label.trim() : 'Bloqueo',
    hostPattern,
    pathPattern: typeof rule.path_pattern === 'string' && rule.path_pattern.trim() !== '' ? rule.path_pattern.trim().toLowerCase() : '/*',
    windowMode: rule.window_mode === 'blocked' ? 'blocked' : 'allowed',
    timezone: typeof rule.timezone === 'string' && rule.timezone.trim() !== '' ? rule.timezone.trim() : 'America/Lima',
    windows,
  };
}

export function ruleMatchesLocation(rule: BlockRule, hostname: string, pathname: string): boolean {
  return hostMatches(rule.hostPattern, hostname.toLowerCase()) && pathMatches(rule.pathPattern, normalizePath(pathname));
}

function hostMatches(pattern: string, hostname: string): boolean {
  if (pattern.startsWith('*.')) {
    const bare = pattern.slice(2);
    return hostname === bare || hostname.endsWith('.'.concat(bare));
  }
  return hostname === pattern;
}

function pathMatches(pattern: string, pathname: string): boolean {
  if (pattern === '/*' || pattern === '/') return true;
  if (pattern.endsWith('/*')) {
    const prefix = pattern.slice(0, -2);
    return pathname === prefix || pathname.startsWith(prefix.concat('/'));
  }
  return pathname === pattern;
}

function normalizePath(pathname: string): string {
  const clean = pathname.toLowerCase().split('?')[0].split('#')[0];
  return clean.replace(/\/+$/, '') || '/';
}

/** true cuando el instante cae dentro de alguna ventana de la regla. */
export function isWithinRuleWindow(rule: BlockRule, date: Date = new Date()): boolean {
  const parts = getZonedParts(rule.timezone, date);
  const minutes = parts.hour * 60 + parts.minute;

  return rule.windows.some((window) => {
    if (window.dayOfWeek !== parts.weekday) return false;
    const start = toMinutes(window.start);
    const end = toMinutes(window.end);
    return minutes >= start && minutes < end;
  });
}

export function isBlockedByRule(rule: BlockRule, date: Date = new Date()): boolean {
  const inside = isWithinRuleWindow(rule, date);
  return rule.windowMode === 'allowed' ? !inside : inside;
}

/**
 * Siguiente instante en el que la regla cambia de estado. Se calcula sobre los
 * limites reales de las ventanas de los proximos ocho dias, asi que soporta
 * horarios distintos por dia sin recorrer minuto a minuto.
 */
export function getNextRuleChange(rule: BlockRule, date: Date = new Date()): Date | null {
  if (rule.windows.length === 0) return null;

  const current = isBlockedByRule(rule, date);
  const parts = getZonedParts(rule.timezone, date);

  for (let offset = 0; offset < LOOKAHEAD_DAYS; offset += 1) {
    const civil = addCivilDays(parts.year, parts.month, parts.day, offset);
    const weekday = civilWeekday(civil);

    const boundaries = rule.windows
      .filter((window) => window.dayOfWeek === weekday)
      .flatMap((window) => [toMinutes(window.start), toMinutes(window.end)])
      .filter((minutes) => minutes >= 0 && minutes <= MINUTES_PER_DAY)
      .sort((a, b) => a - b);

    for (const minutes of boundaries) {
      const candidate = zonedCivilToUtc(rule.timezone, civil.year, civil.month, civil.day, Math.floor(minutes / 60), minutes % 60);
      if (candidate.getTime() <= date.getTime()) continue;
      if (isBlockedByRule(rule, candidate) !== current) return candidate;
    }
  }

  return null;
}

/**
 * Identificador del periodo bloqueado en curso. El desbloqueo forzoso se ata a
 * el: cuando el periodo cambia, la excepcion deja de ser valida.
 */
export function getRulePeriodId(rule: BlockRule, date: Date = new Date()): string | null {
  if (!isBlockedByRule(rule, date)) return null;
  const nextChange = getNextRuleChange(rule, date);
  return `${rule.id}:${nextChange ? nextChange.toISOString() : 'sin-fin'}`;
}

/** Horario del dia en curso, para mostrarlo en el popup y en el overlay. */
export function describeRuleDay(rule: BlockRule, date: Date = new Date()): string {
  const parts = getZonedParts(rule.timezone, date);
  const windows = rule.windows
    .filter((window) => window.dayOfWeek === parts.weekday)
    .sort((a, b) => toMinutes(a.start) - toMinutes(b.start))
    .map((window) => `${window.start} h - ${window.end} h`);

  if (windows.length > 0) return windows.join(' / ');

  return rule.windowMode === 'allowed' ? 'Sin horario permitido hoy' : 'Sin bloqueo hoy';
}

export function evaluateRuleSet(ruleSet: BlockRuleSet, hostname: string, pathname: string, date: Date = new Date()): RuleEvaluation {
  const rule = ruleSet.rules.find((candidate) => ruleMatchesLocation(candidate, hostname, pathname)) ?? null;

  if (!rule) {
    return { rule: null, matched: false, blockedBySchedule: false, nextChangeAt: null, remainingMs: 0, remainingLabel: formatRemainingDuration(0), periodId: null, scheduleLabel: '' };
  }

  return evaluateRule(rule, date);
}

export function evaluateRule(rule: BlockRule, date: Date = new Date()): RuleEvaluation {
  const blockedBySchedule = isBlockedByRule(rule, date);
  const nextChangeAt = getNextRuleChange(rule, date);
  const remainingMs = blockedBySchedule && nextChangeAt ? Math.max(0, nextChangeAt.getTime() - date.getTime()) : 0;

  return {
    rule,
    matched: true,
    blockedBySchedule,
    nextChangeAt,
    remainingMs,
    remainingLabel: formatRemainingDuration(remainingMs),
    periodId: blockedBySchedule ? getRulePeriodId(rule, date) : null,
    scheduleLabel: describeRuleDay(rule, date),
  };
}

export function formatRemainingDuration(ms: number): string {
  const totalSeconds = Math.max(0, Math.floor(ms / 1000));
  const hours = String(Math.floor(totalSeconds / 3600)).padStart(2, '0');
  const minutes = String(Math.floor((totalSeconds % 3600) / 60)).padStart(2, '0');
  const seconds = String(totalSeconds % 60).padStart(2, '0');
  return `${hours}:${minutes}:${seconds}`;
}

export interface ZonedParts {
  year: number;
  month: number;
  day: number;
  hour: number;
  minute: number;
  second: number;
  weekday: number;
}

const WEEKDAYS: Record<string, number> = { Sun: 0, Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6 };

export function getZonedParts(timeZone: string, date: Date = new Date()): ZonedParts {
  const formatter = new Intl.DateTimeFormat('en-US', {
    timeZone,
    hour12: false,
    weekday: 'short',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  });

  const values = Object.fromEntries(formatter.formatToParts(date).map((part) => [part.type, part.value]));

  return {
    year: Number(values.year),
    month: Number(values.month),
    day: Number(values.day),
    // Intl devuelve 24 para medianoche en algunos motores.
    hour: Number(values.hour) % 24,
    minute: Number(values.minute),
    second: Number(values.second),
    weekday: WEEKDAYS[String(values.weekday)] ?? 0,
  };
}

/**
 * Convierte una fecha civil de la zona indicada a un instante UTC real. Dos
 * pasadas bastan incluso con cambio de horario de verano (Lima no lo tiene,
 * pero la zona es configurable desde el panel).
 */
export function zonedCivilToUtc(timeZone: string, year: number, month: number, day: number, hour: number, minute: number): Date {
  const naive = Date.UTC(year, month - 1, day, hour, minute, 0, 0);
  let guess = new Date(naive - zoneOffsetMs(timeZone, new Date(naive)));
  guess = new Date(naive - zoneOffsetMs(timeZone, guess));
  return guess;
}

function zoneOffsetMs(timeZone: string, date: Date): number {
  const parts = getZonedParts(timeZone, date);
  const asUtc = Date.UTC(parts.year, parts.month - 1, parts.day, parts.hour, parts.minute, parts.second, 0);
  return asUtc - Math.floor(date.getTime() / 1000) * 1000;
}

function addCivilDays(year: number, month: number, day: number, offset: number): { year: number; month: number; day: number } {
  const date = new Date(Date.UTC(year, month - 1, day + offset, 12, 0, 0, 0));
  return { year: date.getUTCFullYear(), month: date.getUTCMonth() + 1, day: date.getUTCDate() };
}

function civilWeekday(civil: { year: number; month: number; day: number }): number {
  return new Date(Date.UTC(civil.year, civil.month - 1, civil.day, 12, 0, 0, 0)).getUTCDay();
}

function toMinutes(value: string): number {
  const [hour, minute] = value.split(':');
  return Number(hour) * 60 + Number(minute);
}

function normalizeTime(value: unknown): string | null {
  if (typeof value !== 'string') return null;
  const match = /^(\d{1,2}):(\d{2})/.exec(value.trim());
  if (!match) return null;
  const hour = Number(match[1]);
  const minute = Number(match[2]);
  if (hour > 24 || minute > 59) return null;
  return `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
}

function asRecord(value: unknown): Record<string, unknown> | null {
  return typeof value === 'object' && value !== null && !Array.isArray(value) ? (value as Record<string, unknown>) : null;
}
