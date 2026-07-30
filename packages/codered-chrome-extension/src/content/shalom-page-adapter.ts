import { normalizeText } from '../utils/format';

export type ShalomChannel = 'AUTO' | 'TERRESTRE' | 'AEREO';

const HEADER_SELECTORS = ['.mdl-layout__header-row', 'header .mdl-layout__header-row', 'header', '.mdl-layout__header'];
const CHANNEL_SELECTOR = 'button, a, [role="tab"], [onclick], .mdl-tabs__tab';
const ACTIVE_CLASSES = ['active', 'is-active', 'selected'];

export function findHeader(root: ParentNode = document): HTMLElement | null {
  for (const selector of HEADER_SELECTORS) {
    const element = root.querySelector(selector);
    if (element instanceof HTMLElement) return element;
  }
  return null;
}

export function detectActiveChannel(root: ParentNode = document): Exclude<ShalomChannel, 'AUTO'> {
  const candidates = Array.from(root.querySelectorAll(CHANNEL_SELECTOR)).filter((element): element is HTMLElement => element instanceof HTMLElement);
  const scored = candidates
    .map((element) => ({ element, channel: detectElementChannel(element), active: isActiveChannelElement(element) }))
    .filter((item): item is { element: HTMLElement; channel: Exclude<ShalomChannel, 'AUTO'>; active: boolean } => item.channel !== null);
  const active = scored.find((item) => item.active);
  return active?.channel ?? scored[0]?.channel ?? 'TERRESTRE';
}

export function bindChannelButtons(root: ParentNode, onChange: (channel: Exclude<ShalomChannel, 'AUTO'>) => void): void {
  const candidates = Array.from(root.querySelectorAll(CHANNEL_SELECTOR)).filter((element): element is HTMLElement => element instanceof HTMLElement);
  for (const element of candidates) {
    const channel = detectElementChannel(element);
    if (!channel || element.dataset.coderedChannelBound === 'true') continue;
    element.dataset.coderedChannelBound = 'true';
    element.addEventListener('click', () => window.setTimeout(() => onChange(detectActiveChannel(root)), 0));
  }
}

function detectElementChannel(element: HTMLElement): Exclude<ShalomChannel, 'AUTO'> | null {
  const haystack = normalizeText([element.getAttribute('title'), element.getAttribute('aria-label'), element.getAttribute('onclick'), element.textContent].filter(Boolean).join(' '));
  if (haystack.includes('terrestre')) return 'TERRESTRE';
  if (haystack.includes('aereo')) return 'AEREO';
  return null;
}

function isActiveChannelElement(element: HTMLElement): boolean {
  return element.getAttribute('aria-selected') === 'true' || ACTIVE_CLASSES.some((className) => element.classList.contains(className));
}
