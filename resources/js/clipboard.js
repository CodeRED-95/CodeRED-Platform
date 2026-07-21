export function fallbackCopy(text, documentRef = globalThis.document) {
  const textarea = documentRef.createElement("textarea");
  textarea.value = text;
  textarea.setAttribute("readonly", "");
  textarea.style.position = "fixed";
  textarea.style.opacity = "0";
  documentRef.body.appendChild(textarea);
  textarea.select();
  const copied = documentRef.execCommand("copy");
  textarea.remove();
  if (!copied) throw new Error("No se pudo copiar el contenido");
}

export async function copyToClipboard(value, environment = globalThis) {
  const text = value == null ? "" : String(value);
  if (environment.isSecureContext && environment.navigator?.clipboard?.writeText) {
    await environment.navigator.clipboard.writeText(text);
  } else {
    fallbackCopy(text, environment.document);
  }
  return text;
}

export function registerClipboardListener(target = globalThis.document, environment = globalThis) {
  if (!target || target.documentElement?.dataset.coderedClipboard === "ready") return;
  if (target.documentElement) target.documentElement.dataset.coderedClipboard = "ready";
  target.addEventListener("codered-copy", async (event) => {
    try {
      await copyToClipboard(event.detail?.value, environment);
      environment.dispatchEvent(new CustomEvent("toast", { detail: { type: "success", message: "Copiado al portapapeles" } }));
    } catch {
      environment.dispatchEvent(new CustomEvent("toast", { detail: { type: "error", message: "No se pudo copiar el contenido" } }));
    }
  });
}
