/**
 * Adaptador del selector de destino de la SPA nueva (sysnewos, Vue 3).
 *
 * `/service-order/items` ya no usa el `<select>` + Chosen que maneja
 * `agency-selector.ts`, sino un combobox propio:
 *
 * - El valor real es el ID de la agencia, guardado en el store de Vue. En el
 *   DOM solo se ve el texto del input, asi que no se puede escribir el valor:
 *   hay que provocar la seleccion como la haria una persona.
 * - La lista se teletransporta a <body> con `position: fixed`, y cada opcion
 *   es `<li data-key="{id}">` con el texto `Dpt.X, Prov.Y, Dist.Z, Ag.NOMBRE`.
 * - El commit va por `mousedown`, NO por `click`.
 * - El filtro del sitio solo compara `startsWith` contra departamento,
 *   provincia y distrito: escribir el nombre de la agencia no encuentra nada.
 * - `allowDeselect` esta activo: repetir la seleccion sobre la agencia ya
 *   elegida la borra.
 * - El store se actualiza un tick despues del mousedown, de modo que el exito
 *   se confirma observando el DOM, no leyendo inmediatamente.
 */
import type { Agency } from '../models/agency';
import type { ShalomChannel } from './shalom-page-adapter';

export const DESTINATION_INPUT_ID = 'destination-agency';
const OPTION_LIST_SELECTOR = 'ul[class*="bg-white"][class*="border"]';
const SELECTED_OPTION_CLASS = 'bg-emerald-500';
const CHANNEL_RADIO_NAME = 'transport-type';

export type ComboboxSelectionResult =
  | { success: true; value: string; channel: Exclude<ShalomChannel, 'AUTO'> | null; alreadySelected: boolean }
  | { success: false; reason: 'no-destination-input' | 'missing-agency-id' | 'option-not-found' | 'not-confirmed'; message: string };

/**
 * Nada de `instanceof` contra los constructores globales: el content script
 * puede recibir nodos de otro realm (un iframe) y ahi `instanceof` da false
 * aunque el elemento sea perfectamente valido.
 */
function asElement(node: unknown): HTMLElement | null {
  return node && typeof (node as HTMLElement).tagName === 'string' ? (node as HTMLElement) : null;
}

function asInput(node: unknown): HTMLInputElement | null {
  const element = asElement(node);
  return element && element.tagName === 'INPUT' ? (element as HTMLInputElement) : null;
}

export function findDestinationCombobox(root: ParentNode = document): HTMLInputElement | null {
  const input = asInput(root.querySelector(`#${DESTINATION_INPUT_ID}`));
  return input && !input.disabled ? input : null;
}

/**
 * Canal activo en la SPA nueva: dos radios en vez de las pestanas del sitio
 * antiguo. Devuelve null si el bloque de modalidad aun no esta en el DOM.
 */
export function detectComboboxChannel(root: ParentNode = document): Exclude<ShalomChannel, 'AUTO'> | null {
  const radios = Array.from(root.querySelectorAll(`input[type="radio"][name="${CHANNEL_RADIO_NAME}"]`))
    .map(asInput)
    .filter((radio): radio is HTMLInputElement => radio !== null);
  const checked = radios.find((radio) => radio.checked);
  if (!checked) return null;

  const value = `${checked.value} ${checked.id}`.toLowerCase();
  if (value.includes('aerial') || value.includes('aereo') || value.includes('aéreo')) return 'AEREO';
  if (value.includes('terrestrial') || value.includes('terrestre')) return 'TERRESTRE';
  return null;
}

/** Etiqueta que el sitio muestra para una agencia una vez seleccionada. */
export function buildDestinationLabel(agency: Agency): string {
  return `Dpt.${agency.department ?? ''}, Prov.${agency.province ?? ''}, Dist.${agency.district ?? ''}, Ag.${agency.name}`;
}

/**
 * Consultas que pueden hacer aparecer la agencia en la lista, en el mismo
 * orden en que el sitio agrupa los resultados.
 */
export function buildFilterQueries(agency: Agency): string[] {
  return [agency.department, agency.province, agency.district]
    .map((value) => (value ?? '').trim().toLowerCase())
    .filter((value) => value !== '');
}

export function findOptionByKey(key: string, root: ParentNode = document): HTMLElement | null {
  return asElement(root.querySelector(`${OPTION_LIST_SELECTOR} > li[data-key="${cssEscape(key)}"]`));
}

export function isOptionSelected(option: HTMLElement): boolean {
  return option.classList.contains(SELECTED_OPTION_CLASS);
}

export interface ComboboxDeps {
  /** Inyectable en pruebas para no depender de temporizadores reales. */
  wait?: (ms: number) => Promise<void>;
  timeoutMs?: number;
}

export async function selectAgencyInCombobox(
  root: ParentNode = document,
  agency: Agency,
  deps: ComboboxDeps = {},
): Promise<ComboboxSelectionResult> {
  const wait = deps.wait ?? ((ms: number) => new Promise<void>((resolve) => setTimeout(resolve, ms)));
  const timeoutMs = deps.timeoutMs ?? 1500;
  const input = findDestinationCombobox(root);
  if (!input) return { success: false, reason: 'no-destination-input', message: 'No se encontró el campo de agencia de destino.' };

  const key = agency.externalId === null || agency.externalId === undefined ? '' : String(agency.externalId).trim();
  if (key === '') return { success: false, reason: 'missing-agency-id', message: 'La agencia no tiene identificador de Shalom.' };

  const label = buildDestinationLabel(agency);
  const channel = detectComboboxChannel(root);

  // Ya seleccionada: no se toca. Repetir el mousedown la deseleccionaria.
  if (input.value.trim() === label) {
    return { success: true, value: key, channel, alreadySelected: true };
  }

  for (const query of buildFilterQueries(agency)) {
    input.focus();
    await wait(60);
    setInputValue(input, query);
    await wait(80);

    const option = await waitFor(() => findOptionByKey(key, root), wait, timeoutMs);
    if (!option) continue;

    if (isOptionSelected(option)) {
      closeList(input);
      return { success: true, value: key, channel, alreadySelected: true };
    }

    option.dispatchEvent(createEvent(option, 'MouseEvent', 'mousedown', { bubbles: true, cancelable: true }));

    const confirmed = await waitFor(() => (input.value.trim() === label ? true : null), wait, timeoutMs);
    if (confirmed) return { success: true, value: key, channel, alreadySelected: false };

    return {
      success: false,
      reason: 'not-confirmed',
      message: 'Shalom Control no confirmó el cambio de la agencia de destino.',
    };
  }

  closeList(input);

  return {
    success: false,
    reason: 'option-not-found',
    message: 'La agencia está registrada, pero no aparece entre los destinos disponibles de Shalom Control.',
  };
}

/**
 * El input usa v-model, que escucha el evento `input`. Asignar `value` a secas
 * no lo actualiza: hay que pasar por el setter nativo para que el valor llegue
 * al framework.
 */
function setInputValue(input: HTMLInputElement, value: string): void {
  // El prototipo del propio elemento, no el global: asi el setter pertenece al
  // mismo realm que el input.
  const setter = Object.getOwnPropertyDescriptor(Object.getPrototypeOf(input) as object, 'value')?.set;
  if (setter) setter.call(input, value);
  else input.value = value;
  input.dispatchEvent(createEvent(input, 'Event', 'input', { bubbles: true }));
}

/** Esc cierra la lista sin tocar la seleccion vigente. */
function closeList(input: HTMLInputElement): void {
  input.dispatchEvent(createEvent(input, 'KeyboardEvent', 'keydown', { key: 'Escape', bubbles: true, cancelable: true }));
  input.blur();
}

type EventCtorName = 'Event' | 'MouseEvent' | 'KeyboardEvent';

/** Construye eventos con el constructor del realm del propio elemento. */
function createEvent(element: Element, ctorName: EventCtorName, type: string, init: Record<string, unknown>): Event {
  const view = element.ownerDocument?.defaultView as unknown as Record<string, unknown> | null;
  const Ctor = (view?.[ctorName] ?? (globalThis as unknown as Record<string, unknown>)[ctorName]) as
    | (new (type: string, init?: Record<string, unknown>) => Event)
    | undefined;

  return Ctor ? new Ctor(type, init) : new Event(type, init as EventInit);
}

async function waitFor<T>(probe: () => T | null, wait: (ms: number) => Promise<void>, timeoutMs: number): Promise<T | null> {
  const deadline = Date.now() + timeoutMs;
  for (;;) {
    const value = probe();
    if (value) return value;
    if (Date.now() >= deadline) return null;
    await wait(50);
  }
}

function cssEscape(value: string): string {
  return value.replace(/["\\]/g, '\\$&');
}
