/**
 * Contexto huerfano de un content script.
 *
 * Cuando la extension se actualiza o se recarga, los content scripts ya
 * inyectados en las pestanas abiertas siguen vivos pero pierden su canal con
 * la extension: cualquier llamada a `chrome.*` lanza "Extension context
 * invalidated". Comprobar `typeof chrome.storage.local.get === 'function'` no
 * sirve, porque los objetos siguen ahi; lo que desaparece es `chrome.runtime.id`.
 */
export function isExtensionContextAlive(): boolean {
  try {
    return typeof chrome !== 'undefined' && Boolean(chrome.runtime?.id);
  } catch {
    return false;
  }
}

export function isContextInvalidatedError(error: unknown): boolean {
  const message = error instanceof Error ? error.message : String(error ?? '');
  return /context invalidated|extension context/i.test(message);
}
