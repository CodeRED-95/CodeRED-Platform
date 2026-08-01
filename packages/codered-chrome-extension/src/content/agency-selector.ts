import type { Agency } from '../models/agency';
import { normalizeText } from '../utils/format';
import type { ShalomChannel } from './shalom-page-adapter';

type SelectFailure = { reason: 'multiple-active-selects'; count: number } | { reason: 'no-destination-select' };
type SelectionResult =
  | { success: true; value: string; channel: Exclude<ShalomChannel, 'AUTO'> }
  | { success: false; reason: 'no-destination-select' | 'multiple-active-selects' | 'missing-channel-text' | 'option-not-found' | 'multiple-matching-options'; message: string; channel?: Exclude<ShalomChannel, 'AUTO'> };

export function findActiveDestinationSelect(root: ParentNode = document): HTMLSelectElement | SelectFailure | null {
  const candidates = Array.from(root.querySelectorAll('select[id*="osProDestino"]')).filter((element): element is HTMLSelectElement => element instanceof HTMLSelectElement);
  const active = candidates.filter(isUsableSelect);
  if (active.length === 0) return null;
  if (active.length === 1) return active[0];
  const visibleEnabled = active.filter((select) => !select.disabled && isVisible(select));
  if (visibleEnabled.length === 1) return visibleEnabled[0];
  return { reason: 'multiple-active-selects', count: visibleEnabled.length || active.length };
}

export function selectAgencyInDestination(root: ParentNode, agency: Agency, requestedChannel: ShalomChannel): SelectionResult {
  const channel = requestedChannel === 'AUTO' ? 'TERRESTRE' : requestedChannel;
  const chosenText = channel === 'TERRESTRE' ? agency.terrestrialText : agency.airText;
  if (!chosenText) return { success: false, reason: 'missing-channel-text', message: `La agencia no tiene texto Chosen para ${channel}.`, channel };

  const select = findActiveDestinationSelect(root);
  if (!select) return { success: false, reason: 'no-destination-select', message: 'No hay un campo de destino disponible en la pantalla actual.', channel };
  if (!(select instanceof HTMLSelectElement)) return { success: false, reason: 'multiple-active-selects', message: 'Hay multiples campos de destino activos; no se selecciono ninguno.', channel };

  const option = findMatchingOption(select, chosenText, agency);
  if (isOptionFailure(option)) {
    if (option.reason === 'option-not-found') {
      return { success: false, reason: 'option-not-found', message: `La agencia esta registrada en CodeRED Platform, pero no esta disponible en el selector actual de Shalom Control (${channel}).`, channel };
    }
    return { success: false, reason: 'multiple-matching-options', message: `Hay multiples opciones coincidentes para ${channel}; no se cambio el selector.`, channel };
  }

  select.value = option.value;
  option.selected = true;
  select.dispatchEvent(new Event('input', { bubbles: true }));
  select.dispatchEvent(new Event('change', { bubbles: true }));
  updateChosenDom(select);
  triggerChosenUpdated(select);

  if (select.value !== option.value) return { success: false, reason: 'option-not-found', message: 'Shalom Control no confirmo el cambio del selector.', channel };
  return { success: true, value: option.value, channel };
}

type OptionFailure = { reason: 'option-not-found' | 'multiple-matching-options' };

function isOptionFailure(value: HTMLOptionElement | OptionFailure): value is OptionFailure {
  return 'reason' in value;
}

function findMatchingOption(select: HTMLSelectElement, chosenText: string, agency: Agency): HTMLOptionElement | OptionFailure {
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

function isUsableSelect(select: HTMLSelectElement): boolean {
  return !select.disabled && !select.hidden && select.getAttribute('aria-hidden') !== 'true' && isVisible(select);
}

function isVisible(element: HTMLElement): boolean {
  for (let current: HTMLElement | null = element; current; current = current.parentElement) {
    if (current.hidden || current.getAttribute('aria-hidden') === 'true') return false;
    const style = current.getAttribute('style')?.replace(/\s+/g, '').toLowerCase() ?? '';
    if (style.includes('display:none') || style.includes('visibility:hidden')) return false;
  }
  return true;
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
