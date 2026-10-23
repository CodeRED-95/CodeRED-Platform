(() => {
  let captureModule;
  let last = '';
  let timer;
  const module = () => captureModule ??= import(chrome.runtime.getURL('capture.mjs'));
  const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

  async function stableCapture() {
    const {readSnapshot, fingerprint, nativeModal} = await module();
    const list = nativeModal(document)?.querySelector('ul[role="listbox"]');
    if (!list) throw new Error('Abre la lista nativa terrestre sin filtro.');
    const oldScroll = list.scrollTop;
    try {
      let previous;
      for (let attempt = 0; attempt < 6; attempt++) {
        list.scrollTop = list.scrollHeight;
        list.dispatchEvent(new Event('scroll'));
        await wait(1000);
        const snapshot = readSnapshot(document, location.href);
        const key = fingerprint(snapshot);
        if (previous === key) return {...snapshot, stable_reads: 2};
        previous = key;
      }
      throw new Error('No se estabilizó el final de la lista. No se realizarán modificaciones.');
    } finally { list.scrollTop = oldScroll; }
  }

  chrome.runtime.onMessage.addListener((message, sender, reply) => {
    if (sender.id !== chrome.runtime.id || message.type !== 'capture-now') return;
    (async () => {
      const {nativeModal} = await module();
      // Solo abre el selector. Nunca agrega productos, cambia transporte ni elige destinos.
      if (!nativeModal(document)) {
        const target = document.querySelector('#destination-agency');
        if (!target || target.disabled) throw new Error('Agrega un producto y habilita el destino en Shalom para poder leer su lista.');
        target.click();
        await wait(500);
      }
      const snapshot = await stableCapture();
      const result = await chrome.runtime.sendMessage({type: 'snapshot', snapshot});
      if (!result?.ok) throw new Error(result?.error || 'No se pudo registrar la captura.');
      return {ok: true};
    })().then(reply, (error) => reply({ok: false, error: error.message}));
    return true;
  });

  // Captura pasiva: solo lee una lista que el usuario ya abrió, vacía y terrestre.
  const observer = new MutationObserver(() => {
    clearTimeout(timer);
    timer = setTimeout(async () => {
      try {
        const {fingerprint} = await module();
        const snapshot = await stableCapture();
        const key = fingerprint(snapshot);
        if (key === last) return;
        const result = await chrome.runtime.sendMessage({type: 'snapshot', snapshot});
        if (result?.ok) last = key;
      } catch { /* Una lista cerrada, aérea o filtrada no es una captura válida. */ }
    }, 900);
  });
  observer.observe(document.body, {subtree: true, childList: true});
})();
