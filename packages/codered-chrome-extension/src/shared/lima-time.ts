export const LIMA_TIME_ZONE = 'America/Lima';

export interface LimaTimeParts {
  year: number;
  month: number;
  day: number;
  hour: number;
  minute: number;
  second: number;
}

export interface ServiceOrderScheduleState {
  lockedBySchedule: boolean;
  locked: boolean;
  reason: 'schedule' | 'manual' | 'schedule+manual' | 'unlocked';
  nextAllowedAt: Date | null;
  remainingMs: number;
  remainingLabel: string;
}

const ALLOWED_START_MINUTES = 8 * 60;
const ALLOWED_END_MINUTES = 20 * 60 + 5;

export function getLimaTimeParts(date: Date = new Date()): LimaTimeParts {
  const formatter = new Intl.DateTimeFormat('en-GB', {
    timeZone: LIMA_TIME_ZONE,
    hour12: false,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  });

  const parts = formatter.formatToParts(date);
  const values = Object.fromEntries(parts.map((part) => [part.type, part.value]));

  return {
    year: Number(values.year),
    month: Number(values.month),
    day: Number(values.day),
    hour: Number(values.hour),
    minute: Number(values.minute),
    second: Number(values.second),
  };
}

export function isWithinAllowedServiceOrderWindow(date: Date = new Date()): boolean {
  const { hour, minute } = getLimaTimeParts(date);
  const currentMinutes = hour * 60 + minute;
  return currentMinutes >= ALLOWED_START_MINUTES && currentMinutes < ALLOWED_END_MINUTES;
}

export function getServiceOrderScheduleState(date: Date = new Date(), manualLocked = false): ServiceOrderScheduleState {
  const lockedBySchedule = !isWithinAllowedServiceOrderWindow(date);
  const locked = lockedBySchedule || manualLocked;
  const nextAllowedAt = lockedBySchedule ? getNextAllowedServiceOrderDate(date) : null;
  const remainingMs = nextAllowedAt ? Math.max(0, nextAllowedAt.getTime() - date.getTime()) : 0;
  return {
    lockedBySchedule,
    locked,
    reason: lockedBySchedule && manualLocked ? 'schedule+manual' : lockedBySchedule ? 'schedule' : manualLocked ? 'manual' : 'unlocked',
    nextAllowedAt,
    remainingMs,
    remainingLabel: formatRemainingDuration(remainingMs),
  };
}

export function getNextAllowedServiceOrderDate(date: Date = new Date()): Date {
  const parts = getLimaTimeParts(date);
  const minutes = parts.hour * 60 + parts.minute;
  const next = new Date(date.getTime());
  if (minutes < ALLOWED_START_MINUTES) {
    next.setHours(8, 0, 0, 0);
    return next;
  }

  if (minutes > ALLOWED_END_MINUTES || (minutes === ALLOWED_END_MINUTES && parts.second > 0)) {
    next.setDate(next.getDate() + 1);
    next.setHours(8, 0, 0, 0);
    return next;
  }

  next.setHours(parts.hour, parts.minute, parts.second, 0);
  return next;
}

export function formatRemainingDuration(ms: number): string {
  const totalSeconds = Math.max(0, Math.floor(ms / 1000));
  const hours = String(Math.floor(totalSeconds / 3600)).padStart(2, '0');
  const minutes = String(Math.floor((totalSeconds % 3600) / 60)).padStart(2, '0');
  const seconds = String(totalSeconds % 60).padStart(2, '0');
  return `${hours}:${minutes}:${seconds}`;
}
