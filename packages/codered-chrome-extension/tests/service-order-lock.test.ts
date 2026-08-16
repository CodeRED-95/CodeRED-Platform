import { describe, expect, it } from 'vitest';
import { getNextAllowedServiceOrderDate, getRestrictedPeriodId, getServiceOrderScheduleState, isWithinAllowedServiceOrderWindow } from '../src/shared/lima-time';
import { resolvePageContext, isNeutralShalomSearchPath } from '../src/content/shalom-host';

describe('service order schedule', () => {
  it.each([
    ['07:59', '2026-08-16T12:59:00.000Z', true],
    ['08:00', '2026-08-16T13:00:00.000Z', false],
    ['12:00', '2026-08-16T17:00:00.000Z', false],
    ['20:04', '2026-08-17T01:04:00.000Z', false],
    ['20:05', '2026-08-17T01:05:00.000Z', true],
    ['23:59', '2026-08-17T04:59:00.000Z', true],
    ['00:00', '2026-08-17T05:00:00.000Z', true],
  ])('checks %s Lima correctly', (_label, iso, locked) => {
    const state = getServiceOrderScheduleState(new Date(iso));
    expect(isWithinAllowedServiceOrderWindow(new Date(iso))).toBe(!locked);
    expect(state.locked).toBe(locked);
  });

  it('keeps manual lock as a secondary blocker during allowed hours', () => {
    const allowed = getServiceOrderScheduleState(new Date('2026-08-16T17:00:00.000Z'), true);
    expect(allowed.locked).toBe(true);
    expect(allowed.reason).toBe('manual');
  });

  it('keeps schedule priority over manual unlock outside hours', () => {
    const blocked = getServiceOrderScheduleState(new Date('2026-08-17T02:00:00.000Z'), false);
    expect(blocked.locked).toBe(true);
    expect(blocked.reason).toBe('schedule');
  });

  it('identifies the current restricted period around midnight correctly', () => {
    expect(getRestrictedPeriodId(new Date('2026-08-17T02:00:00.000Z'))).toBe('2026-08-16');
    expect(getRestrictedPeriodId(new Date('2026-08-17T01:05:00.000Z'))).toBe('2026-08-16');
    expect(getRestrictedPeriodId(new Date('2026-08-16T13:00:00.000Z'))).toBe(null);
  });

  it('moves the next allowed date to 08:00 the following day at the exact 20:05 boundary', () => {
    expect(getNextAllowedServiceOrderDate(new Date('2026-08-17T01:05:00.000Z')).toISOString()).toBe('2026-08-17T13:00:00.000Z');
  });
});

describe('service order URL scope', () => {
  it('targets only /service-order on sysnewos.shalomcontrol.com', () => {
    expect(resolvePageContext('/service-order')).toEqual({ site: 'sysnewos', module: 'service-order', mode: 'neutral' });
    expect(resolvePageContext('/service-order/')).toEqual({ site: 'sysnewos', module: 'service-order', mode: 'neutral' });
    expect(isNeutralShalomSearchPath('/service-order?x=1#hash')).toBe(true);
  });
});
