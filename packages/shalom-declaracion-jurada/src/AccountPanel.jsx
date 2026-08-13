import { useCallback, useEffect, useState } from 'react'
import { Check, KeyRound, ShoppingCart, Trash2, X } from 'lucide-react'

const fieldClass = 'rounded-lg border border-slate-300 bg-white text-slate-900 placeholder:text-slate-400 dark:border-slate-600 dark:bg-slate-800 dark:text-white dark:placeholder:text-slate-400 dark:[color-scheme:dark]'

const readReceipt = file => new Promise((resolve, reject) => {
  if (!file || !['image/png', 'image/jpeg', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) {
    reject(new Error('Adjunta un comprobante PNG, JPG o WebP de hasta 5 MB.'))
    return
  }
  const reader = new FileReader()
  reader.onload = () => resolve({ data: reader.result, name: file.name })
  reader.onerror = () => reject(new Error('No se pudo leer el comprobante.'))
  reader.readAsDataURL(file)
})

const api = async (url, options) => {
  const response = await fetch(url, options)
  const payload = await response.json()
  if (!response.ok) throw new Error(payload.message)
  return payload
}

const sendJson = (url, method, body) => api(url, {
  method,
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify(body)
})

const AccountPanel = ({ user, onClose, onUserChange }) => {
  const [requests, setRequests] = useState([])
  const [users, setUsers] = useState([])
  const [userPage, setUserPage] = useState(1)
  const [userPagination, setUserPagination] = useState({ page: 1, pages: 1, total: 0 })
  const [store, setStore] = useState({ packages: [], methods: [] })
  const [config, setConfig] = useState({ packages: [], methods: [], hasApiKey: false })
  const [buying, setBuying] = useState(false)
  const [purchase, setPurchase] = useState({ packageId: '', paymentMethodId: '', reference: '' })
  const [receipt, setReceipt] = useState(null)
  const [coderedUrl, setCoderedUrl] = useState('')
  const [message, setMessage] = useState('')

  useEffect(() => {
    fetch('/api/config').then(response => response.json()).then(payload => setCoderedUrl(payload.coderedUrl || '')).catch(() => {})
  }, [])

  const load = useCallback(async () => {
    try {
      if (user.canManage) {
        const [userData, requestData, configData] = await Promise.all([
          api(`/api/admin/users?page=${userPage}`), api('/api/admin/credit-requests'), api('/api/admin/config')
        ])
        setUsers(userData.users)
        setUserPagination({ page: userData.page, pages: userData.pages, total: userData.total })
        setRequests(requestData.requests)
        setConfig(configData)
      } else {
        const [requestData, storeData] = await Promise.all([api('/api/credit-requests'), api('/api/store')])
        setRequests(requestData.requests)
        setStore(storeData)
        onUserChange(requestData.user)
      }
    } catch (error) { setMessage(error.message) }
  }, [onUserChange, user.canManage, userPage])

  useEffect(() => {
    const timer = window.setTimeout(load, 0)
    return () => window.clearTimeout(timer)
  }, [load])

  const act = async (action, success) => {
    try { await action(); setMessage(success); await load() } catch (error) { setMessage(error.message) }
  }

  const selectedMethod = store.methods.find(method => method.id === Number(purchase.paymentMethodId))
  const selectReceipt = async event => {
    try {
      setReceipt(await readReceipt(event.target.files?.[0]))
      setMessage('')
    } catch (error) {
      setReceipt(null)
      setMessage(error.message)
    }
  }
  const submitPurchase = () => act(async () => {
    if (!receipt) throw new Error('Adjunta la captura de la transacción.')
    await sendJson('/api/credit-requests', 'POST', {
      ...purchase,
      receiptImage: receipt.data,
      receiptName: receipt.name
    })
    setPurchase({ packageId: '', paymentMethodId: '', reference: '' })
    setReceipt(null)
  }, 'Compra y comprobante enviados para aprobación.')

  return <div className="fixed inset-0 z-[200] overflow-auto bg-slate-950/70 p-4 backdrop-blur-sm">
    <div className="mx-auto max-w-6xl rounded-2xl bg-white p-6 text-slate-900 shadow-2xl dark:bg-slate-900 dark:text-slate-100">
      <div className="flex items-start justify-between"><div><h2 className="text-xl font-black">{user.canManage ? 'Panel administrador' : 'Mi cuenta'}</h2><p className="text-sm text-slate-500 dark:text-slate-400">{user.email}</p></div><button onClick={onClose} className="rounded-lg p-2 hover:bg-slate-100 dark:hover:bg-slate-800" aria-label="Cerrar"><X /></button></div>
      {message && <p className="my-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">{message}</p>}
      {coderedUrl && <p className="mt-2 text-xs text-slate-500 dark:text-slate-400">Tu cuenta, contraseña y permisos se administran en <a href={`${coderedUrl}/profile`} target="_blank" rel="noopener noreferrer" className="font-bold text-red-600 hover:underline">CodeRED Platform</a>.</p>}

      {!user.canManage ? <>
        <div className="my-5 grid grid-cols-2 gap-3 sm:grid-cols-3">
          <Stat value={user.credits} label="Consultas disponibles" color="emerald" />
          <Stat value={user.queriesUsed} label="Consultas utilizadas" color="blue" />
          <Stat value={(user.creditBatches || []).filter(batch => batch.active).length} label="Lotes vigentes" color="slate" />
        </div>
        <BatchBalances batches={user.creditBatches || []}/>
        <button onClick={() => setBuying(value => !value)} className="flex items-center gap-2 rounded-xl bg-red-600 px-5 py-3 font-black text-white"><ShoppingCart size={19}/> COMPRAR CONSULTAS</button>
        {buying && <div className="mt-5 rounded-xl border p-4 dark:border-slate-700">
          <h3 className="mb-3 font-black">Selecciona un paquete</h3>
          {store.packages.length ? <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">{store.packages.map(item => <button key={item.id} onClick={() => setPurchase(prev => ({ ...prev, packageId: item.id }))} className={`rounded-xl border p-4 text-left ${Number(purchase.packageId) === item.id ? 'border-red-500 bg-red-50 dark:bg-red-950/30' : 'dark:border-slate-700'}`}><b>{item.name}</b><p className="text-2xl font-black">S/ {Number(item.price).toFixed(2)}</p><p className="text-sm">{item.credits} consultas · {item.validityDays} días</p></button>)}</div> : <p className="text-sm text-slate-500">El administrador aún no configuró paquetes.</p>}
          <h3 className="mb-2 mt-5 font-black">Método de pago</h3>
          <select value={purchase.paymentMethodId} onChange={event => setPurchase(prev => ({ ...prev, paymentMethodId: event.target.value }))} className={`w-full p-3 ${fieldClass}`}><option value="">Seleccionar</option>{store.methods.map(method => <option key={method.id} value={method.id}>{method.name}</option>)}</select>
          {selectedMethod && <p className="mt-2 whitespace-pre-line rounded-lg bg-slate-100 p-3 text-sm dark:bg-slate-800">{selectedMethod.instructions}</p>}
          {selectedMethod?.imageData && <img src={selectedMethod.imageData} alt={`Información de pago de ${selectedMethod.name}`} className="mt-3 max-h-64 w-full rounded-xl border object-contain p-2 dark:border-slate-700"/>}
          <input value={purchase.reference} onChange={event => setPurchase(prev => ({ ...prev, reference: event.target.value }))} placeholder="Número de operación o referencia" className={`mt-3 w-full p-3 ${fieldClass}`} />
          <label className="mt-3 block text-sm font-bold">Captura de la transacción</label>
          <input key={receipt?.name || 'empty-receipt'} type="file" accept="image/png,image/jpeg,image/webp" onChange={selectReceipt} className={`mt-2 w-full file:mr-3 file:rounded-md file:border-0 file:bg-red-600 file:px-3 file:py-2 file:font-bold file:text-white ${fieldClass}`} />
          <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">PNG, JPG o WebP. Máximo 5 MB.{receipt ? ` Seleccionado: ${receipt.name}` : ''}</p>
          <button onClick={submitPurchase} className="mt-3 rounded-lg bg-emerald-600 px-5 py-3 font-bold text-white">ENVIAR COMPROBANTE</button>
        </div>}
        <History requests={requests}/>
      </> : <AdminContent users={users} requests={requests} config={config} act={act} pagination={userPagination} onPageChange={setUserPage}/>}
    </div>
  </div>
}

const statColors = {
  emerald: 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-200',
  blue: 'bg-blue-50 text-blue-800 dark:bg-blue-950/30 dark:text-blue-200',
  slate: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200'
}
const Stat = ({ value, label, color }) => <div className={`rounded-xl p-4 ${statColors[color]}`}><b className="text-xl">{value}</b><p className="text-xs">{label}</p></div>

const BatchBalances = ({ batches }) => <div className="mb-5"><h3 className="mb-2 font-black">Saldos por compra</h3><div className="grid gap-2 sm:grid-cols-2">{batches.length ? batches.map(batch => <div key={batch.id} className={`rounded-lg border p-3 text-sm dark:border-slate-700 ${batch.active ? '' : 'opacity-55'}`}><b>{batch.creditsRemaining} de {batch.creditsTotal} consultas</b><p className="text-xs text-slate-500 dark:text-slate-400">Vence: {batch.expiresAt ? new Date(batch.expiresAt).toLocaleString('es-PE') : 'Sin vencimiento'} · {batch.active ? 'Vigente' : 'Vencido o agotado'}</p></div>) : <p className="text-sm text-slate-500">No tienes lotes de consultas.</p>}</div></div>

const History = ({ requests }) => <div className="mt-6"><h3 className="mb-2 font-black">Mis compras</h3><div className="space-y-2">{requests.map(item => <div key={item.id} className="flex flex-wrap justify-between gap-2 rounded-lg border p-3 text-sm dark:border-slate-700"><span>{item.packageName || `${item.credits} consultas`} · {item.paymentMethod || 'Manual'} · S/ {Number(item.amount || 0).toFixed(2)}{item.expiresAt ? ` · Vence ${new Date(item.expiresAt).toLocaleDateString('es-PE')} · Restan ${item.creditsRemaining}` : ''}</span><b>{item.status}</b></div>)}</div></div>

const AdminContent = ({ users, requests, config, act, pagination, onPageChange }) => {
  const [tab, setTab] = useState('settings')
  const [apiKey, setApiKey] = useState('')
  const [telegramBotToken, setTelegramBotToken] = useState('')
  const [telegramChatId, setTelegramChatId] = useState('')
  const [packageForm, setPackageForm] = useState({ name: '', credits: 50, price: 10, validityDays: 30 })
  const [methodForm, setMethodForm] = useState({ name: '', instructions: '', imageData: '', imageName: '' })
  const selectMethodImage = async event => {
    try {
      const image = await readReceipt(event.target.files?.[0])
      setMethodForm(previous => ({ ...previous, imageData: image.data, imageName: image.name }))
    } catch (error) {
      window.alert(error.message)
    }
  }
  const tabs = [['settings', 'Configuración'], ['users', `Usuarios (${pagination.total})`], ['catalog', 'Planes y pagos'], ['purchases', 'Compras']]
  return <div className="mt-6">
    <nav className="mb-6 flex gap-2 overflow-x-auto border-b border-slate-200 pb-2 dark:border-slate-700">{tabs.map(([value, label]) => <button key={value} onClick={() => setTab(value)} className={`whitespace-nowrap rounded-lg px-4 py-2 text-sm font-bold ${tab === value ? 'bg-red-600 text-white' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200'}`}>{label}</button>)}</nav>
    {tab === 'settings' && <div className="space-y-8">
      <section><h3 className="mb-3 text-lg font-black">API de consulta DNI</h3><div className="flex gap-2"><div className="relative flex-1"><KeyRound className="absolute left-3 top-3 text-slate-400" size={18}/><input type="password" value={apiKey} onChange={event => setApiKey(event.target.value)} placeholder={config.hasApiKey ? 'API configurada; escribe para reemplazarla' : 'Clave API'} className={`w-full py-3 pl-10 ${fieldClass}`}/></div><button onClick={() => act(() => sendJson('/api/admin/config', 'PATCH', { apiKey }), 'API guardada.')} className="rounded-lg bg-red-600 px-5 font-bold text-white">Guardar</button></div>{config.coderedDniBridge && <p className="mt-2 text-xs text-slate-500 dark:text-slate-400">Actualmente las consultas se resuelven vía la API de CodeRED Platform; esta clave solo se usa como respaldo si esa integración no está disponible.</p>}</section>
      <section><h3 className="mb-3 text-lg font-black">Notificaciones de Telegram</h3><div className="grid gap-2 md:grid-cols-[1fr_220px_auto]"><input type="password" value={telegramBotToken} onChange={event => setTelegramBotToken(event.target.value)} placeholder={config.telegramConfigured ? 'Bot configurado; escribe para reemplazar el token' : 'Token del bot'} className={`p-3 ${fieldClass}`}/><input value={telegramChatId} onChange={event => setTelegramChatId(event.target.value)} placeholder={config.telegramChatId ? `Chat actual: ${config.telegramChatId}` : 'Chat ID'} className={`p-3 ${fieldClass}`}/><button onClick={() => act(() => sendJson('/api/admin/config', 'PATCH', { telegramBotToken, ...(telegramChatId.trim() && { telegramChatId }) }), 'Telegram guardado.')} className="rounded-lg bg-red-600 px-5 py-3 font-bold text-white">Guardar</button></div><p className="mt-2 text-xs text-slate-500 dark:text-slate-400">El bot debe pertenecer al chat indicado para poder enviar los comprobantes.</p></section>
    </div>}
    {tab === 'users' && <section><h3 className="mb-3 text-lg font-black">Usuarios y consultas</h3><div className="space-y-3">{users.map(target => <AdminUser key={target.coderedUserId} user={target} onAdd={values => act(() => sendJson(`/api/admin/users/${target.coderedUserId}`, 'PATCH', values), 'Lote de consultas agregado.')} onRemove={values => act(() => sendJson(`/api/admin/users/${target.coderedUserId}`, 'PATCH', values), 'Consultas retiradas.')}/>)}</div><Pagination {...pagination} onChange={onPageChange}/></section>}
    {tab === 'catalog' && <div className="space-y-8">
      <section><h3 className="mb-3 text-lg font-black">Paquetes y precios</h3><div className="grid gap-2 md:grid-cols-5"><input placeholder="Nombre" value={packageForm.name} onChange={e=>setPackageForm({...packageForm,name:e.target.value})} className={`p-2 ${fieldClass}`}/><input type="number" value={packageForm.credits} onChange={e=>setPackageForm({...packageForm,credits:Number(e.target.value)})} className={`p-2 ${fieldClass}`}/><input type="number" step="0.01" value={packageForm.price} onChange={e=>setPackageForm({...packageForm,price:Number(e.target.value)})} className={`p-2 ${fieldClass}`}/><input type="number" value={packageForm.validityDays} onChange={e=>setPackageForm({...packageForm,validityDays:Number(e.target.value)})} className={`p-2 ${fieldClass}`}/><button onClick={()=>act(()=>sendJson('/api/admin/packages','POST',packageForm),'Paquete creado.')} className="rounded bg-slate-900 p-2 font-bold text-white dark:bg-red-600">Agregar</button></div><Catalog items={config.packages} type="packages" act={act}/></section>
      <section><h3 className="mb-3 text-lg font-black">Métodos de pago</h3><div className="grid gap-2 md:grid-cols-[180px_1fr_220px_auto]"><input placeholder="Yape, Plin, transferencia..." value={methodForm.name} onChange={e=>setMethodForm({...methodForm,name:e.target.value})} className={`p-2 ${fieldClass}`}/><textarea placeholder="Número, banco e instrucciones" value={methodForm.instructions} onChange={e=>setMethodForm({...methodForm,instructions:e.target.value})} className={`p-2 ${fieldClass}`}/><div><input type="file" accept="image/png,image/jpeg,image/webp" onChange={selectMethodImage} className={`w-full p-2 text-xs ${fieldClass}`}/>{methodForm.imageData && <img src={methodForm.imageData} alt="Vista previa del método" className="mt-2 h-20 w-full rounded object-contain"/>}</div><button onClick={()=>act(()=>sendJson('/api/admin/payment-methods','POST',methodForm),'Método creado.')} className="rounded bg-slate-900 p-2 font-bold text-white dark:bg-red-600">Agregar</button></div><Catalog items={config.methods} type="payment-methods" act={act}/></section>
    </div>}
    {tab === 'purchases' && <section><h3 className="mb-3 text-lg font-black">Solicitudes de compra</h3><div className="space-y-2">{requests.map(item => <div key={item.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg border p-3 text-sm dark:border-slate-700"><span>{item.email} · {item.packageName || item.credits} · {item.paymentMethod} · Ref. {item.reference} · S/ {Number(item.amount || 0).toFixed(2)} · {item.receiptSent ? 'Comprobante enviado' : 'Sin comprobante'}{item.expiresAt ? ` · Vence ${new Date(item.expiresAt).toLocaleDateString('es-PE')} · Restan ${item.creditsRemaining}` : ''}</span><b>{item.status}</b><span className="flex gap-2">{item.status === 'pending' && <><button onClick={()=>act(()=>sendJson(`/api/admin/credit-requests/${item.id}`,'PATCH',{status:'approved'}),'Compra aprobada.')} className="rounded bg-emerald-600 p-2 text-white" title="Aprobar"><Check size={16}/></button><button onClick={()=>act(()=>sendJson(`/api/admin/credit-requests/${item.id}`,'PATCH',{status:'rejected'}),'Compra rechazada.')} className="rounded bg-amber-600 p-2 text-white" title="Rechazar"><X size={16}/></button></>}<button onClick={()=>window.confirm('¿Eliminar esta compra? Si fue aprobada, también se eliminará su saldo restante.') && act(()=>sendJson(`/api/admin/credit-requests/${item.id}`,'DELETE',{}),'Compra eliminada.')} className="rounded bg-red-600 p-2 text-white" title="Eliminar compra"><Trash2 size={16}/></button></span></div>)}</div></section>}
  </div>
}

const AdminUser = ({ user, onAdd, onRemove }) => {
  const [addCredits, setAddCredits] = useState(10)
  const [expiresAt, setExpiresAt] = useState('')
  return <div className="rounded-xl border p-3 dark:border-slate-700"><div className="grid gap-2 md:grid-cols-[1fr_120px_170px_auto_auto] md:items-center"><div><b>{user.email}</b><p className="text-xs text-slate-500">Disponibles: {user.credits} · Usadas: {user.queriesUsed}</p></div><input type="number" min="1" value={addCredits} onChange={e=>setAddCredits(e.target.value)} aria-label="Cantidad de consultas" className={`p-2 ${fieldClass}`}/><input type="date" value={expiresAt} onChange={e=>setExpiresAt(e.target.value)} aria-label="Vencimiento del nuevo lote" className={`p-2 ${fieldClass}`}/><button onClick={()=>onAdd({addCredits:Number(addCredits),expiresAt:expiresAt ? new Date(`${expiresAt}T23:59:59`).toISOString():null})} className="rounded bg-slate-900 px-4 py-2 font-bold text-white dark:bg-red-600">Agregar lote</button><button onClick={()=>window.confirm(`¿Retirar ${addCredits} consultas a ${user.email}?`) && onRemove({removeCredits:Number(addCredits)})} disabled={user.credits < 1} className="rounded bg-amber-600 px-4 py-2 font-bold text-white disabled:opacity-40">Quitar</button></div><div className="mt-2 flex flex-wrap gap-2">{(user.creditBatches || []).filter(batch => batch.active).map(batch => <span key={batch.id} className="rounded-full bg-slate-100 px-2 py-1 text-xs dark:bg-slate-800">{batch.creditsRemaining}/{batch.creditsTotal} · {batch.expiresAt ? new Date(batch.expiresAt).toLocaleDateString('es-PE') : 'Sin fecha'}</span>)}</div></div>
}

const Pagination = ({ page, pages, onChange }) => <div className="mt-4 flex items-center justify-center gap-3"><button disabled={page <= 1} onClick={() => onChange(page - 1)} className="rounded-lg bg-slate-100 px-3 py-2 text-sm font-bold disabled:opacity-40 dark:bg-slate-800">Anterior</button><span className="text-sm">Página {page} de {pages}</span><button disabled={page >= pages} onClick={() => onChange(page + 1)} className="rounded-lg bg-slate-100 px-3 py-2 text-sm font-bold disabled:opacity-40 dark:bg-slate-800">Siguiente</button></div>

const Catalog = ({ items, type, act }) => <div className="mt-3 flex flex-wrap gap-2">{items.map(item => <div key={item.id} className="rounded-lg border p-3 text-sm dark:border-slate-700">{item.imageData && <img src={item.imageData} alt={item.name} className="mb-2 h-24 w-full rounded object-contain"/>}<b>{item.name}</b>{item.credits && <span> · {item.credits} consultas · S/ {Number(item.price).toFixed(2)} · {item.validityDays} días</span>}<button onClick={()=>act(()=>sendJson(`/api/admin/${type}/${item.id}`,'PATCH',{active:!item.active}), 'Catálogo actualizado.')} className="ml-3 text-red-600">{item.active ? 'Desactivar':'Activar'}</button></div>)}</div>

export default AccountPanel
