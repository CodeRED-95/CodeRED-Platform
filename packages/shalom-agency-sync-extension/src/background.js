const API = 'https://platform.codered.lat/api/v1';
const SHALOM = 'https://sysnewos.shalomcontrol.com/service-order/items';
const PREFIX = '/admin/agencies/native-sync';
let refreshing;
let queue = Promise.resolve();
const serial = (task) => {
  const next = queue.then(task, task);
  queue = next.catch(() => {});
  return next;
};
const get = async () => (await chrome.storage.session.get('state')).state ?? {auto: false};
const put = (state) => chrome.storage.session.set({state});

async function http(path, body, token) {
  let response;
  try {
    response = await fetch(API + path, {method: body === undefined ? 'GET' : 'POST',
      headers: {'Accept': 'application/json', ...(body === undefined ? {} : {'Content-Type': 'application/json'}), ...(token ? {Authorization: `Bearer ${token}`} : {})},
      body: body === undefined ? undefined : JSON.stringify(body), credentials: 'omit', cache: 'no-store',
      redirect: 'error', signal: AbortSignal.timeout(30000)});
  } catch { throw new Error('No se pudo conectar con Platform. Comprueba tu conexión y vuelve a intentar.'); }
  const json = await response.json().catch(() => ({}));
  if (!response.ok) {
    const error = new Error(Object.values(json.errors ?? {}).flat().join(' ') || json.message || `Platform respondió ${response.status}.`);
    error.status = response.status;
    throw error;
  }
  return json.data;
}

async function refresh() {
  if (!refreshing) refreshing = (async () => {
    const state = await get();
    if (!state.auth) throw new Error('Inicia sesión de nuevo.');
    try {
      const auth = await http('/auth/refresh', {refresh_token: state.auth.refresh_token});
      await put({...await get(), auth});
    } catch (error) {
      if ([401, 403].includes(error.status)) await put({...await get(), auth: null, user: null, auto: false, plan: null});
      throw error;
    }
  })().finally(() => { refreshing = null; });
  return refreshing;
}

async function api(path, body) {
  let state = await get();
  if (!state.auth) throw new Error('Inicia sesión con tu cuenta administradora de Platform.');
  if (Date.parse(state.auth.expires_at) <= Date.now() + 30000) { await refresh(); state = await get(); }
  try { return await http(path, body, state.auth.access_token); }
  catch (error) {
    if (error.status === 401) {
      await refresh();
      return http(path, body, (await get()).auth.access_token);
    }
    if (error.status === 403) await put({...await get(), auto: false, plan: null});
    throw error;
  }
}

async function compare() {
  const state = await get();
  if (state.pending) throw new Error('Hay una aplicación con resultado pendiente. Comprueba su resultado antes de comparar nuevamente.');
  if (!state.snapshot) throw new Error('Captura primero la lista nativa terrestre.');
  await put({...state, plan: null, error: null});
  const plan = await api(PREFIX + '/preview', state.snapshot);
  await put({...await get(), plan});
  return plan;
}

async function apply(previewId) {
  const state = await get();
  if (state.pending) throw new Error('Comprueba primero el resultado de la operación pendiente.');
  if (!state.plan?.can_apply) throw new Error('Compara la lista y resuelve los bloqueos antes de aplicar.');
  if (!previewId || previewId !== state.plan.preview_id) throw new Error('La comparación cambió desde la confirmación. Revisa y confirma el plan actual.');
  let receipt;
  try { receipt = await api(PREFIX + '/apply', {preview_id: previewId}); }
  catch (error) {
    if (!error.status || error.status >= 500) {
      await put({...await get(), auto: false, pending: previewId});
      throw new Error('Resultado pendiente de comprobar: Platform pudo haber aplicado los cambios. La automatización se apagó. Usa «Comprobar resultado» antes de continuar.');
    }
    throw error;
  }
  await put({...await get(), receipt, plan: null, error: null});
  return receipt;
}

async function handle(message, sender) {
  if (sender.id !== chrome.runtime.id) throw new Error('Origen no autorizado.');
  if (message.type === 'snapshot') {
    if (!sender.tab || sender.url?.split(/[?#]/)[0] !== SHALOM) throw new Error('Solo se admiten capturas desde Shalom PRODUCTOS.');
    const s = message.snapshot;
    if (s?.source_url !== SHALOM || s.channel !== 'terrestrial' || s.filter !== '' || s.complete !== true
      || s.end_observed !== true || s.stable_reads < 2
      || !Array.isArray(s.entries) || s.entries.length < 100 || s.entries.length > 3000 || s.total !== s.entries.length) throw new Error('Captura no válida.');
    const state = await get();
    await put({...state, snapshot: s, plan: null, error: null});
    if (state.auto && state.auth && !state.pending) {
      const plan = await compare();
      if (plan.can_apply) await apply(plan.preview_id);
    }
    return;
  }
  if (sender.tab && sender.url?.split(/[?#]/)[0] !== chrome.runtime.getURL('dashboard.html')) throw new Error('Acción disponible únicamente en el panel de la extensión.');
  switch (message.type) {
    case 'state': {
      const state = await get();
      return {...state, auth: undefined};
    }
    case 'login': {
      const previous = await get();
      if (previous.auth) await api('/auth/logout', {}).catch(() => {});
      await put({auto: false, snapshot: previous.snapshot});
      const auth = await http('/auth/login', {email: message.email, password: message.password,
        application: 'platform', device_name: 'Sincronizador de agencias Shalom', platform: 'Chrome', client_version: '1.0.0'});
      await put({auth, auto: false, snapshot: previous.snapshot});
      try {
        const info = await api(PREFIX + '/status');
        await put({...await get(), user: info.user});
      } catch (error) {
        await api('/auth/logout', {}).catch(() => {});
        await put({auto: false, snapshot: previous.snapshot});
        throw error;
      }
      return;
    }
    case 'logout': {
      // El apagado local no depende de la red; los tokens se conservan si queda revocación pendiente.
      await put({...await get(), auto: false});
      try { await api('/auth/logout', {}); }
      catch { throw new Error('Automatización apagada. No se pudo revocar la sesión en Platform: vuelve a cerrar sesión cuando haya conexión o revócala desde Platform.'); }
      await put({auto: false, snapshot: (await get()).snapshot});
      return;
    }
    case 'validate': {
      if ((await get()).auth) {
        const info = await api(PREFIX + '/status');
        await put({...await get(), user: info.user});
      }
      return;
    }
    case 'compare': return compare();
    case 'apply': return apply(message.preview_id);
    case 'result': {
      const state = await get();
      if (!state.pending) throw new Error('No hay una operación pendiente.');
      const result = await api(PREFIX + '/result', {preview_id: state.pending});
      if (!result.receipt) throw new Error('Todavía no hay un recibo confirmado. Revisa la auditoría y los estados en Platform antes de descartar esta operación pendiente.');
      await put({...await get(), receipt: result.receipt, pending: null, plan: null, error: null});
      return;
    }
    case 'clear-pending': {
      await put({...await get(), pending: null, plan: null, auto: false, error: null});
      return;
    }
    case 'auto': {
      if (message.enabled !== true) { await put({...await get(), auto: false}); return; }
      if ((await get()).pending) throw new Error('Comprueba la operación pendiente antes de activar la automatización.');
      await api(PREFIX + '/status');
      await put({...await get(), auto: message.enabled === true});
      return;
    }
    case 'capture': {
      const tabs = await chrome.tabs.query({url: SHALOM + '*'});
      if (tabs.length !== 1) throw new Error(tabs.length ? 'Deja abierta una sola pestaña de Shalom PRODUCTOS para identificar el origen correcto.' : 'Abre Shalom en PRODUCTOS y selecciona TERRESTRE.');
      // No esperar aquí: la respuesta del content script envía otra operación serializada.
      chrome.tabs.sendMessage(tabs[0].id, {type: 'capture-now'}).then(async (result) => {
        if (!result?.ok) await serial(async () => put({...await get(), error: result?.error || 'No se pudo capturar.'}));
      }).catch(async () => serial(async () => put({...await get(), error: 'Recarga la pestaña de Shalom para habilitar la nueva extensión.'})));
      return;
    }
    default: throw new Error('Operación desconocida.');
  }
}

chrome.runtime.onMessage.addListener((message, sender, reply) => {
  serial(() => handle(message, sender)).then((data) => reply({ok: true, data}),
    async (error) => {
      if (message.type !== 'state') await put({...await get(), error: error.message});
      reply({ok: false, error: error.message});
    });
  return true;
});
chrome.action.onClicked.addListener(() => chrome.tabs.create({url: chrome.runtime.getURL('dashboard.html')}));
chrome.storage.session.setAccessLevel({accessLevel: 'TRUSTED_CONTEXTS'});
