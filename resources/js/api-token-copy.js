export function selectTokenText(element) {
  if (!element || typeof document === "undefined") return false;

  const selection = window.getSelection?.();
  if (!selection) return false;

  const range = document.createRange();
  range.selectNodeContents(element);
  selection.removeAllRanges();
  selection.addRange(range);
  element.focus?.({ preventScroll: true });

  return true;
}

export async function writeTokenToClipboard(token, clipboard = globalThis.navigator?.clipboard) {
  if (!clipboard?.writeText) {
    throw new Error("Clipboard API no disponible.");
  }

  await clipboard.writeText(token);
}

export function codeRedTokenCopy(token) {
  return {
    copied: false,
    copying: false,
    resetTimer: null,

    async copy() {
      if (this.copying) return;

      this.copying = true;
      try {
        await writeTokenToClipboard(token);
        this.copied = true;
        this.$dispatch("toast", {
          tone: "success",
          message: "Token copiado correctamente.",
        });
        window.clearTimeout(this.resetTimer);
        this.resetTimer = window.setTimeout(() => {
          this.copied = false;
        }, 2000);
      } catch {
        this.select();
        this.$dispatch("toast", {
          tone: "warning",
          message: "No fue posible copiar automáticamente. El token quedó seleccionado para copiarlo manualmente.",
        });
      } finally {
        this.copying = false;
      }
    },

    select() {
      selectTokenText(this.$refs.tokenText);
    },

    destroy() {
      window.clearTimeout(this.resetTimer);
    },
  };
}
