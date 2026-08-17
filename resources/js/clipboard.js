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
  const notify = (type, message) => environment.dispatchEvent(new CustomEvent("toast", { detail: { type, message } }));
  const copy = async (value, label = "Contenido") => {
    try {
      await copyToClipboard(value, environment);
      notify("success", `${label} copiado al portapapeles.`);
    } catch {
      notify("error", "No se pudo copiar al portapapeles.");
    }
  };

  target.addEventListener("codered-copy", (event) => {
    void copy(event.detail?.value, event.detail?.label ?? "Contenido");
  });

  target.addEventListener("click", (event) => {
    const trigger = event.target?.closest?.("[data-codered-copy]");
    if (!trigger) return;
    event.preventDefault();
    void copy(
      trigger.getAttribute("data-codered-copy") ?? "",
      trigger.getAttribute("data-codered-copy-label") ?? "Contenido",
    );
  });
}
