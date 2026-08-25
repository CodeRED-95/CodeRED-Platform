import { describe, expect, it } from 'vitest';
import {
  DEFAULT_BLOCK_RULE,
  evaluateRule,
  getNextRuleChange,
  getRulePeriodId,
  isBlockedByRule,
  parseBlockRuleSet,
  ruleMatchesLocation,
  type BlockRule,
} from '../src/shared/block-rules';
import { resolvePageContext, isNeutralShalomSearchPath } from '../src/content/shalom-host';

describe('default service order schedule', () => {
  it.each([
    ['07:59', '2026-08-16T12:59:00.000Z', true],
    ['08:00', '2026-08-16T13:00:00.000Z', false],
    ['12:00', '2026-08-16T17:00:00.000Z', false],
    ['20:04', '2026-08-17T01:04:00.000Z', false],
    ['20:05', '2026-08-17T01:05:00.000Z', true],
    ['23:59', '2026-08-17T04:59:00.000Z', true],
    ['00:00', '2026-08-17T05:00:00.000Z', true],
  ])('checks %s Lima correctly', (_label, iso, locked) => {
    expect(isBlockedByRule(DEFAULT_BLOCK_RULE, new Date(iso))).toBe(locked);
  });

  it('moves the next change to 08:00 the following day at the exact 20:05 boundary', () => {
    expect(getNextRuleChange(DEFAULT_BLOCK_RULE, new Date('2026-08-17T01:05:00.000Z'))?.toISOString()).toBe('2026-08-17T13:00:00.000Z');
  });

  it('keeps a stable restricted period id across the whole blocked stretch', () => {
    const early = getRulePeriodId(DEFAULT_BLOCK_RULE, new Date('2026-08-17T01:05:00.000Z'));
    const late = getRulePeriodId(DEFAULT_BLOCK_RULE, new Date('2026-08-17T02:00:00.000Z'));
    expect(early).toBe(late);
    expect(early).toBe('0:2026-08-17T13:00:00.000Z');
    expect(getRulePeriodId(DEFAULT_BLOCK_RULE, new Date('2026-08-16T13:00:00.000Z'))).toBe(null);
  });
});

describe('per-day schedules from the platform panel', () => {
  // Lunes a sabado 08:00-20:05, domingo 08:00-17:05.
  const rule: BlockRule = {
    ...DEFAULT_BLOCK_RULE,
    id: 7,
    windows: [
      ...[1, 2, 3, 4, 5, 6].map((dayOfWeek) => ({ dayOfWeek, start: '08:00', end: '20:05' })),
      { dayOfWeek: 0, start: '08:00', end: '17:05' },
    ],
  };

  it('applies the sunday window instead of the weekday one', () => {
    // Domingo 16/08/2026, 17:04 Lima -> permitido; 17:06 -> bloqueado.
    expect(isBlockedByRule(rule, new Date('2026-08-16T22:04:00.000Z'))).toBe(false);
    expect(isBlockedByRule(rule, new Date('2026-08-16T22:06:00.000Z'))).toBe(true);
  });

  it('keeps saturday open until 20:05', () => {
    // Sabado 15/08/2026, 19:00 y 20:30 Lima.
    expect(isBlockedByRule(rule, new Date('2026-08-16T00:00:00.000Z'))).toBe(false);
    expect(isBlockedByRule(rule, new Date('2026-08-16T01:30:00.000Z'))).toBe(true);
  });

  it('announces monday 08:00 as the next change after sunday evening', () => {
    expect(getNextRuleChange(rule, new Date('2026-08-16T23:00:00.000Z'))?.toISOString()).toBe('2026-08-17T13:00:00.000Z');
  });

  it('describes the schedule of the current day', () => {
    expect(evaluateRule(rule, new Date('2026-08-16T22:06:00.000Z')).scheduleLabel).toBe('08:00 h - 17:05 h');
  });
});

describe('blocked-window mode', () => {
  const rule: BlockRule = {
    ...DEFAULT_BLOCK_RULE,
    id: 9,
    windowMode: 'blocked',
    windows: [{ dayOfWeek: 0, start: '12:00', end: '14:00' }],
  };

  it('blocks only inside the configured window', () => {
    expect(isBlockedByRule(rule, new Date('2026-08-16T18:00:00.000Z'))).toBe(true);
    expect(isBlockedByRule(rule, new Date('2026-08-16T20:00:00.000Z'))).toBe(false);
  });
});

describe('rule matching', () => {
  it('matches an exact path only', () => {
    expect(ruleMatchesLocation(DEFAULT_BLOCK_RULE, 'sysnewos.shalomcontrol.com', '/service-order')).toBe(true);
    expect(ruleMatchesLocation(DEFAULT_BLOCK_RULE, 'sysnewos.shalomcontrol.com', '/service-order/')).toBe(true);
    expect(ruleMatchesLocation(DEFAULT_BLOCK_RULE, 'sysnewos.shalomcontrol.com', '/otra-ruta')).toBe(false);
    expect(ruleMatchesLocation(DEFAULT_BLOCK_RULE, 'otro.shalomcontrol.com', '/service-order')).toBe(false);
  });

  it('supports host wildcards and path prefixes', () => {
    const rule: BlockRule = { ...DEFAULT_BLOCK_RULE, destinations: [{ hostPattern: '*.shalomcontrol.com', pathPattern: '/ordenservicio/*' }] };
    expect(ruleMatchesLocation(rule, 'sysnewos.shalomcontrol.com', '/ordenservicio/listar')).toBe(true);
    expect(ruleMatchesLocation(rule, 'shalomcontrol.com', '/ordenservicio')).toBe(true);
    expect(ruleMatchesLocation(rule, 'sysnewos.shalomcontrol.com', '/otra')).toBe(false);
  });
});

describe('varios destinos por regla', () => {
  const rule: BlockRule = {
    ...DEFAULT_BLOCK_RULE,
    destinations: [
      { hostPattern: 'sysnewos.shalomcontrol.com', pathPattern: '/service-order' },
      { hostPattern: 'sysprovincia2.shalomcontrol.com', pathPattern: '/service-order' },
    ],
  };

  it('coincide con cualquiera de los dominios de la regla', () => {
    expect(ruleMatchesLocation(rule, 'sysnewos.shalomcontrol.com', '/service-order')).toBe(true);
    expect(ruleMatchesLocation(rule, 'sysprovincia2.shalomcontrol.com', '/service-order')).toBe(true);
    expect(ruleMatchesLocation(rule, 'syslima.shalomcontrol.com', '/service-order')).toBe(false);
  });

  it('exige que la ruta encaje aunque el dominio coincida', () => {
    expect(ruleMatchesLocation(rule, 'sysprovincia2.shalomcontrol.com', '/otra-ruta')).toBe(false);
  });
});

describe('ruta propia por destino', () => {
  // El caso real que fallaba: dos dominios del mismo grupo, cada uno con su
  // propia ruta dentro de shalomcontrol.com.
  const rule: BlockRule = {
    ...DEFAULT_BLOCK_RULE,
    destinations: [
      { hostPattern: 'sysnewos.shalomcontrol.com', pathPattern: '/service-order' },
      { hostPattern: 'sysprovincia2.shalomcontrol.com', pathPattern: '/ordenservicio/listar' },
    ],
  };

  it('aplica a cada dominio en su propia ruta', () => {
    expect(ruleMatchesLocation(rule, 'sysnewos.shalomcontrol.com', '/service-order')).toBe(true);
    expect(ruleMatchesLocation(rule, 'sysprovincia2.shalomcontrol.com', '/ordenservicio/listar')).toBe(true);
  });

  it('no cruza la ruta de un destino con el dominio del otro', () => {
    expect(ruleMatchesLocation(rule, 'sysnewos.shalomcontrol.com', '/ordenservicio/listar')).toBe(false);
    expect(ruleMatchesLocation(rule, 'sysprovincia2.shalomcontrol.com', '/service-order')).toBe(false);
  });

  it('lee destinations del payload y hereda la ruta de la regla cuando falta', () => {
    const parsed = parseBlockRuleSet({
      data: {
        version: 'v3',
        rules: [{
          id: 5,
          label: 'Mixta',
          path_pattern: '/service-order',
          destinations: [
            { host_pattern: 'sysnewos.shalomcontrol.com' },
            { host_pattern: 'sysprovincia2.shalomcontrol.com', path_pattern: '/ordenservicio/listar' },
          ],
          windows: [],
        }],
      },
    });

    expect(parsed?.rules[0].destinations).toEqual([
      { hostPattern: 'sysnewos.shalomcontrol.com', pathPattern: '/service-order' },
      { hostPattern: 'sysprovincia2.shalomcontrol.com', pathPattern: '/ordenservicio/listar' },
    ]);
  });
});

describe('payload parsing', () => {
  it('reads the platform response and discards malformed rules', () => {
    const parsed = parseBlockRuleSet({
      data: {
        version: 'abc123',
        generated_at: '2026-08-24T00:00:00+00:00',
        rules: [
          { id: 1, label: 'Service Order', host_pattern: 'sysnewos.shalomcontrol.com', host_patterns: ['sysnewos.shalomcontrol.com', 'sysprovincia.shalomcontrol.com'], path_pattern: '/service-order', window_mode: 'allowed', timezone: 'America/Lima', windows: [{ day_of_week: 1, start_time: '08:00:00', end_time: '20:05:00' }] },
          { id: 2, label: 'Sin host' },
        ],
      },
    });

    expect(parsed?.version).toBe('abc123');
    expect(parsed?.rules).toHaveLength(1);
    expect(parsed?.rules[0].windows[0]).toEqual({ dayOfWeek: 1, start: '08:00', end: '20:05' });
    expect(parsed?.rules[0].destinations).toEqual([
      { hostPattern: 'sysnewos.shalomcontrol.com', pathPattern: '/service-order' },
      { hostPattern: 'sysprovincia.shalomcontrol.com', pathPattern: '/service-order' },
    ]);
  });

  it('sigue leyendo payloads antiguos con un solo host_pattern', () => {
    const parsed = parseBlockRuleSet({
      data: { version: 'v1', rules: [{ id: 1, label: 'Legacy', host_pattern: 'sysnewos.shalomcontrol.com', path_pattern: '/service-order', windows: [] }] },
    });

    expect(parsed?.rules[0].destinations).toEqual([{ hostPattern: 'sysnewos.shalomcontrol.com', pathPattern: '/service-order' }]);
  });

  it('returns null when the payload has no rules array', () => {
    expect(parseBlockRuleSet({ data: {} })).toBe(null);
  });
});

describe('service order URL scope', () => {
  it('targets only /service-order on sysnewos.shalomcontrol.com', () => {
    expect(resolvePageContext('/service-order')).toEqual({ site: 'sysnewos', module: 'service-order', mode: 'neutral' });
    expect(resolvePageContext('/service-order/')).toEqual({ site: 'sysnewos', module: 'service-order', mode: 'neutral' });
    expect(isNeutralShalomSearchPath('/service-order?x=1#hash')).toBe(true);
  });
});
