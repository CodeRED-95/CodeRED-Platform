import { normalizeText } from '../utils/format';

export type ShalomChannel = 'AUTO' | 'TERRESTRE' | 'AEREO';

const HEADER_SELECTORS = ['.mdl-layout__header-row', 'header .mdl-layout__header-row', 'header', '.mdl-layout__header'];
const CHANNEL_BUTTON_SELECTORS = [
  'button[title*="Terrestre" i]',
  'button[title*="Aéreo" i]',
  'button[title*="Aereo" i]',
  '[onclick*="TERRESTRE" i]',
  '[onclick*="AEREO" i]',
  '[aria-label*="Terrestre" i]',
  '[aria-label*="Aéreo" i]',
  '[aria-label*="Aereo" i]',
  '.mdl-tabs__tab',
  '[role="tab"]',
  'button',
  'a',
];
const ACTIVE_CLASSES = ['active', 'is-active', 'selected', 'mdl-button--colored', 'mdl-tabs__tab--active'];

export function findHeader(root: ParentNode = document): HTMLElement | null {
  for (const selector of HEADER_SELECTORS) {
    const element = root.querySelector(selector);
    if (element instanceof HTMLElement) return element;
  }
  return null;
}

export interface ChannelDetectionResult {
  channel: Exclude<ShalomChannel, 'AUTO'> | null;
  reason: 'detected' | 'pending' | 'ambiguous';
  candidates: number;
}

export function detectActiveShalomChannel(root: ParentNode = document): Exclude<ShalomChannel, 'AUTO'> | null {
  return detectActiveShalomChannelState(root).channel;
}

export function detectActiveShalomChannelState(root: ParentNode = document): ChannelDetectionResult {
  const candidates = collectChannelCandidates(root);
  const scored = candidates
    .map((element) => ({ element, channel: detectElementChannel(element), active: isActiveChannelElement(element) }))
    .filter((item): item is { element: HTMLElement; channel: Exclude<ShalomChannel, 'AUTO'>; active: boolean } => item.channel !== null);

  const active = scored.find((item) => item.active);
  if (active) {
    return { channel: active.channel, reason: 'detected', candidates: scored.length };
  }

  if (scored.length > 1) {
    return { channel: null, reason: 'ambiguous', candidates: scored.length };
  }

  return { channel: null, reason: 'pending', candidates: scored.length };
}

export const detectActiveChannel = detectActiveShalomChannel;

export function bindChannelButtons(root: ParentNode, onChange: (channel: Exclude<ShalomChannel, 'AUTO'>) => void): void {
  const candidates = collectChannelCandidates(root);
  for (const element of candidates) {
    const channel = detectElementChannel(element);
    if (!channel || element.dataset.coderedChannelBound === 'true') continue;
    element.dataset.coderedChannelBound = 'true';
    element.addEventListener('click', () => {
      window.setTimeout(() => {
        const detection = detectActiveShalomChannelState(root);
        if (detection.channel) onChange(detection.channel);
      }, 0);
    });
  }
}

function collectChannelCandidates(root: ParentNode): HTMLElement[] {
  const seen = new Set<HTMLElement>();
  for (const selector of CHANNEL_BUTTON_SELECTORS) {
    for (const element of Array.from(root.querySelectorAll(selector))) {
      if (element instanceof HTMLElement) seen.add(element);
    }
  }
  return Array.from(seen);
}

function detectElementChannel(element: HTMLElement): Exclude<ShalomChannel, 'AUTO'> | null {
  const haystack = normalizeText([
    element.getAttribute('title'),
    element.getAttribute('aria-label'),
    element.getAttribute('onclick'),
    element.textContent,
    element.className,
    element.querySelector('i, svg, img')?.getAttribute('title'),
    element.querySelector('i, svg, img')?.getAttribute('aria-label'),
    element.querySelector('i, svg, img')?.getAttribute('class'),
  ].filter(Boolean).join(' '));

  if (haystack.includes('terrestre') || haystack.includes('camion') || haystack.includes('truck')) return 'TERRESTRE';
  if (haystack.includes('aereo') || haystack.includes('avion') || haystack.includes('plane') || haystack.includes('flight')) return 'AEREO';
  return null;
}

function isActiveChannelElement(element: HTMLElement): boolean {
  return element.getAttribute('aria-selected') === 'true' || ACTIVE_CLASSES.some((className) => element.classList.contains(className));
}
