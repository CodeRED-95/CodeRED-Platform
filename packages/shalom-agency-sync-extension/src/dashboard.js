const $ = (id) => document.getElementById(id);
const date = (value) => value ? new Intl.DateTimeFormat('es-PE', {dateStyle: 'short', timeStyle: 'medium', timeZone: 'America/Lima'}).format(new Date(value)) : '—';
const status = {active: 'Activa', inactive: 'Inactiva', under_review: 'En revisión', temporarily_closed: 'Cerrada temporalmente', moved: 'Trasladada'};
const available = Boolean(globalThis.chrome?.runtime?.id);
let busy = false;
let state = {};
let renderedPlan;
let lastError;

async function send(type, data = {}) {
  const result = await chrome.runtime.sendMessage({type, ...data});
  if (!result?.ok) throw new Error(result?.error || 'La extensión no pudo completar la operación.');
  return result.data;
}
function message(text, error = false) {
  $('message').hidden = !text;
  $('message').textContent = text || '';
  $('message').className = 'notice' + (error ? ' error' : '');
}
async function run(task, pending) {
  if (busy || !available) return;
  busy = true;
  message(pending);
  render();
  try { await task(); message(''); }
  catch (error) { message(error.message, true); }
  finally { busy = false; await load(); }
}
function textCell(text) { const td = document.createElement('td'); td.textContent = text; return td; }
function render() {
  $('login-form').hidden = Boolean(state.user);
  $('account').hidden = !state.user;
  $('account-name').textContent = state.user?.name ?? '';
  $('account-email').textContent = state.user?.email ?? '';
  $('auto').disabled = busy || !state.user || !available;
  $('auto').checked = state.auto === true;
  $('compare').disabled = busy || !state.user || !state.snapshot || Boolean(state.pending);
  $('capture').disabled = busy || !available;
  $('capture').textContent = state.auto ? 'Capturar y aplicar automáticamente' : 'Capturar lista nativa';
  $('auto-warning').hidden = !state.auto;
  $('pending').hidden = !state.pending;
  $('check-result').disabled = busy;
  $('clear-pending').disabled = busy;
  $('logout').disabled = busy;
  $('login-form').querySelector('button').disabled = busy || !available;
  const snapshot = state.snapshot;
  $('capture-data').hidden = !snapshot;
  if (snapshot) {
    $('native-count').textContent = snapshot.total;
    $('origin').textContent = snapshot.origin_name;
    $('captured-at').textContent = date(snapshot.captured_at) + ' (Perú)';
  }
  const plan = state.plan;
  $('empty').hidden = Boolean(plan);
  $('plan').hidden = !plan;
  $('apply').disabled = busy || !state.user || Boolean(state.pending) || !plan?.can_apply || !plan?.changes?.length || Date.parse(plan?.expires_at) < Date.now();
  if (plan && renderedPlan !== plan.preview_id) {
    renderedPlan = plan.preview_id;
    $('summary').textContent = `${plan.native_count} en Shalom · ${plan.matched_count} coincidentes · ${plan.activate_count} por activar · ${plan.deactivate_count} por desactivar`;
    $('blockers').hidden = !plan.blocked_reasons.length;
    $('blockers').textContent = plan.blocked_reasons.join(' ');
    $('changes').replaceChildren(...plan.changes.map((change) => {
      const row = document.createElement('tr');
      const name = textCell(change.name);
      const location = document.createElement('small'); location.textContent = change.location; name.append(location);
      const target = textCell(change.to === 'active' ? 'Activar' : 'Desactivar'); target.className = change.to === 'active' ? 'activate' : 'deactivate';
      row.append(name, textCell(status[change.from] ?? change.from), target); return row;
    }));
    if (!plan.changes.length) { const row = document.createElement('tr'); const td = textCell('No hay cambios de estado pendientes.'); td.colSpan = 3; row.append(td); $('changes').append(row); }
    $('review-summary').textContent = `${plan.review.length} agencias para revisión manual`;
    $('review-items').replaceChildren(...plan.review.map((item) => { const li = document.createElement('li'); li.textContent = `${item.label} — ${item.reason}`; return li; }));
    $('expiry').textContent = `Origen protegido: ${plan.origin}. Comparación válida hasta ${date(plan.expires_at)} (Perú).`;
  }
  if (!plan) renderedPlan = null;
  $('receipt').hidden = !state.receipt;
  if (state.receipt) {
    const receipt = state.receipt;
    $('receipt-description').textContent = `${receipt.activate_count} agencias activadas y ${receipt.deactivate_count} desactivadas. ${receipt.review_count} pendientes de revisión. Origen conservado: ${receipt.origin}.`;
    $('receipt-time').textContent = `${date(receipt.applied_at)} (Perú) · Administrador #${receipt.user_id} · Operación ${receipt.preview_id}`;
  }
}
async function load() {
  if (!available) return;
  try {
    state = await send('state');
    if (state.error && state.error !== lastError) { lastError = state.error; message(state.error, true); }
    render();
  } catch (error) { message(error.message, true); }
}
$('login-form').addEventListener('submit', (event) => {
  event.preventDefault();
  const credentials = {email: $('email').value.trim(), password: $('password').value};
  $('password').value = '';
  run(() => send('login', credentials), 'Verificando la cuenta administradora…');
});
$('logout').addEventListener('click', () => run(() => send('logout'), 'Cerrando y revocando esta sesión…'));
$('capture').addEventListener('click', () => run(() => send('capture'), 'Solicitando captura a Shalom…'));
$('compare').addEventListener('click', () => run(() => send('compare'), 'Comparando con el catálogo actual de Platform…'));
$('apply').addEventListener('click', () => {
  if (!state.plan || !confirm(`Se activarán ${state.plan.activate_count} agencias y se desactivarán ${state.plan.deactivate_count} en Platform. Se conservará ${state.plan.origin}. ¿Aplicar estos cambios?`)) return;
  const preview_id = state.plan.preview_id;
  run(async () => { await send('apply', {preview_id}); await load(); $('receipt').focus(); }, 'Aplicando los cambios de estado…');
});
$('check-result').addEventListener('click', () => run(async () => {await send('result'); await load(); if (state.receipt) $('receipt').focus();}, 'Comprobando el resultado sin repetir cambios…'));
$('clear-pending').addEventListener('click', () => {
  if (confirm('Descarta el pendiente solo si ya comprobaste los estados y la auditoría en Platform. No revierte cambios. ¿Confirmas que lo revisaste?')) run(() => send('clear-pending'), 'Descartando pendiente revisado…');
});
$('auto').addEventListener('change', () => {
  const enabled = $('auto').checked;
  if (enabled && !confirm('El modo automático podrá activar presentes y desactivar ausentes en Platform al capturar una lista terrestre completa. Las capturas bloqueadas y las agencias en revisión no se aplicarán. ¿Habilitarlo para esta sesión?')) { render(); return; }
  run(() => send('auto', {enabled}), 'Actualizando automatización…');
});
if (available) {
  chrome.storage.onChanged.addListener((changes, area) => { if (area === 'session' && changes.state && !busy) load(); });
  await load();
  run(() => send('validate'), 'Comprobando sesión…');
} else { message('Vista previa de la interfaz. Instala la carpeta dist como extensión descomprimida en Chrome para iniciar sesión y sincronizar.'); render(); }
