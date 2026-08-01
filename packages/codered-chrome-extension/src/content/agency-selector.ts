import type { Agency } from '../models/agency';
import { normalizeText } from '../utils/format';
import type { ShalomChannel } from './shalom-page-adapter';

type SelectFailure = { reason: 'multiple-active-selects'; count: number } | { reason: 'no-destination-select' };
type SelectionResult =
  | { success: true; value: string; channel: Exclude<ShalomChannel, 'AUTO'> }
  | { success: false; reason: 'no-destination-select' | 'multiple-active-selects' | 'missing-channel-text' | 'option-not-found' | 'multiple-matching-options'; message: string; channel?: Exclude<ShalomChannel, 'AUTO'> };

export function getChosenTextForActiveChannel(agency: Agency, channel: Exclude<ShalomChannel, 'AUTO'>): string {
  if (channel === 'TERRESTRE') return agency.terrestrialText ?? '';
  if (channel === 'AEREO') return agency.airText ?? '';
  return '';
}

export function findActiveDestinationSelect(root: ParentNode = document, channel: Exclude<ShalomChannel, 'AUTO'> = 'TERRESTRE'): HTMLSelectElement | SelectFailure | null {
  const candidates = Array.from(root.querySelectorAll('select[id*="osProDestino"]')).filter((element): element is HTMLSelectElement => element instanceof HTMLSelectElement);
  const usable = candidates.filter((select) => !select.disabled && !select.hidden && select.getAttribute('aria-hidden') !== 'true' && isPanelUsable(select));
  const byChosen = usable.filter((select) => {
    const chosen = select.ownerDocument.getElementById(`${select.id}_chosen`);
    return chosen instanceof HTMLElement && isElementVisible(chosen) && matchesChannelContext(chosen, channel);
  });
  if (byChosen.length === 1) return byChosen[0];
  if (byChosen.length > 1) return { reason: 'multiple-active-selects', count: byChosen.length };

  const byPanel = usable.filter((select) => matchesChannelContext(select, channel));
  if (byPanel.length === 1) return byPanel[0];
  if (byPanel.length > 1) return { reason: 'multiple-active-selects', count: byPanel.length };

  const visibleChosen = usable.filter((select) => {
    const chosen = select.ownerDocument.getElementById(`${select.id}_chosen`);
    return chosen instanceof HTMLElement ? isElementVisible(chosen) : isElementVisible(select);
  });
  if (visibleChosen.length === 0) return null;
  if (visibleChosen.length === 1) return visibleChosen[0];
  return { reason: 'multiple-active-selects', count: visibleChosen.length };
}

export function selectAgencyInDestination(root: ParentNode, agency: Agency, requestedChannel: ShalomChannel): SelectionResult {
  const channel = requestedChannel === 'AUTO' ? 'TERRESTRE' : requestedChannel;
  const chosenText = getChosenTextForActiveChannel(agency, channel);
  if (!chosenText) return { success: false, reason: 'missing-channel-text', message: `La agencia no tiene identificador Chosen para el canal ${channel === 'AEREO' ? 'Aéreo' : 'Terrestre'}.`, channel };

  const select = findActiveDestinationSelect(root, channel);
  if (!select) return { success: false, reason: 'no-destination-select', message: 'No se encontró el selector de destino activo de Shalom.', channel };
  if (!(select instanceof HTMLSelectElement)) return { success: false, reason: 'multiple-active-selects', message: 'Se encontraron varios selectores de destino activos y no se realizó ningún cambio.', channel };

  const option = findMatchingOption(select, chosenText, agency);
  if (isOptionFailure(option)) {
    if (option.reason === 'option-not-found') {
      return { success: false, reason: 'option-not-found', message: `La agencia está registrada, pero no está disponible en el selector actual de Shalom (${channel === 'AEREO' ? 'Aéreo' : 'Terrestre'}).`, channel };
    }
    return { success: false, reason: 'multiple-matching-options', message: `Hay múltiples opciones coincidentes para ${channel === 'AEREO' ? 'Aéreo' : 'Terrestre'}; no se cambió el selector.`, channel };
  }

  select.value = option.value;
  option.selected = true;
  select.dispatchEvent(new Event('input', { bubbles: true }));
  select.dispatchEvent(new Event('change', { bubbles: true }));
  updateChosenDom(select);
  triggerChosenUpdated(select);

  if (select.value !== option.value) return { success: false, reason: 'option-not-found', message: 'Shalom Control no confirmó el cambio del selector.', channel };
  return { success: true, value: option.value, channel };
}

type OptionFailure = { reason: 'option-not-found' | 'multiple-matching-options' };

function isOptionFailure(value: HTMLOptionElement | OptionFailure): value is OptionFailure {
  return 'reason' in value;
}

export function findMatchingOption(select: HTMLSelectElement, chosenText: string, agency: Agency): HTMLOptionElement | OptionFailure {
  const options = Array.from(select.options);
  const exact = options.filter((option) => option.text.trim() === chosenText.trim());
  if (exact.length === 1) return exact[0];
  if (exact.length > 1) return { reason: 'multiple-matching-options' };

  const normalizedChosen = normalizeText(chosenText);
  const normalized = options.filter((option) => normalizeText(option.text) === normalizedChosen);
  if (normalized.length === 1) return normalized[0];
  if (normalized.length > 1) return { reason: 'multiple-matching-options' };

  const id = normalizeText(agency.externalId);
  if (id) {
    const byId = options.filter((option) => normalizeText(option.text).includes(id));
    if (byId.length === 1) return byId[0];
    if (byId.length > 1) return { reason: 'multiple-matching-options' };
  }

  return { reason: 'option-not-found' };
}

function matchesChannelContext(element: HTMLElement, channel: Exclude<ShalomChannel, 'AUTO'>): boolean {
  for (let current: HTMLElement | null = element; current; current = current.parentElement) {
    const text = normalizeText([current.id, current.className, current.getAttribute('data-channel'), current.getAttribute('aria-label'), current.getAttribute('title')].filter(Boolean).join(' '));
    if (channel === 'TERRESTRE' && (text.includes('terrestre') || text.includes('camion'))) return true;
    if (channel === 'AEREO' && (text.includes('aereo') || text.includes('avion'))) return true;
  }
  return false;
}

function isPanelUsable(select: HTMLSelectElement): boolean {
  for (let current = select.parentElement; current; current = current.parentElement) {
    if (current.hidden || current.getAttribute('aria-hidden') === 'true') return false;
    const style = current.getAttribute('style')?.replace(/\s+/g, '').toLowerCase() ?? '';
    if (style.includes('display:none') || style.includes('visibility:hidden')) return false;
  }
  return true;
}

function isElementVisible(element: HTMLElement): boolean {
  for (let current: HTMLElement | null = element; current; current = current.parentElement) {
    if (current.hidden || current.getAttribute('aria-hidden') === 'true') return false;
    const style = current.getAttribute('style')?.replace(/\s+/g, '').toLowerCase() ?? '';
    if (style.includes('display:none') || style.includes('visibility:hidden')) return false;
  }
  return element.getClientRects().length > 0 || element.offsetWidth > 0 || element.offsetHeight > 0 || element.getAttribute('data-visible') === 'true' || !element.hasAttribute('style');
}

function triggerChosenUpdated(select: HTMLSelectElement): void {
  const jq = (select.ownerDocument.defaultView as (Window & { jQuery?: (element: HTMLSelectElement) => { trigger: (eventName: string) => void } }) | null)?.jQuery;
  if (typeof jq === 'function') jq(select).trigger('chosen:updated');
}

function updateChosenDom(select: HTMLSelectElement): void {
  const chosenId = `${select.id}_chosen`;
  const chosen = select.ownerDocument.getElementById(chosenId);
  const label = chosen?.querySelector('.chosen-single span, .chosen-container span');
  const selected = select.selectedOptions.item(0);
  if (label && selected) label.textContent = selected.text;
}
