import { useCallback, useEffect, useRef, useState } from 'react'
import { ShieldCheck } from 'lucide-react'

const fieldClass = 'w-full rounded-xl border border-slate-200 p-3 text-slate-900 placeholder:text-slate-400'

const GoogleSignIn = ({ clientId, onAuthenticated, onError }) => {
  const containerRef = useRef(null)

  useEffect(() => {
    if (!clientId) return undefined
    const renderButton = () => {
      if (!window.google?.accounts?.id || !containerRef.current) return
      window.google.accounts.id.initialize({
        client_id: clientId,
        callback: async response => {
          try {
            const result = await fetch('/api/auth/google', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ credential: response.credential })
            })
            const payload = await result.json()
            if (!result.ok) throw new Error(payload.message)
            onAuthenticated(payload.user)
          } catch (error) {
            onError(error.message || 'No se pudo iniciar sesión con Google.')
          }
        }
      })
      containerRef.current.replaceChildren()
      window.google.accounts.id.renderButton(containerRef.current, {
        type: 'standard', theme: 'outline', size: 'large', text: 'continue_with', width: Math.min(360, containerRef.current.clientWidth || 360)
      })
    }
    const existingScript = document.getElementById('google-identity-script')
    if (existingScript) {
      renderButton()
      existingScript.addEventListener('load', renderButton, { once: true })
      return () => existingScript.removeEventListener('load', renderButton)
    }
    const script = document.createElement('script')
    script.id = 'google-identity-script'
    script.src = 'https://accounts.google.com/gsi/client'
    script.async = true
    script.onload = renderButton
    document.head.appendChild(script)
    return undefined
  }, [clientId, onAuthenticated, onError])

  return clientId ? <div ref={containerRef} className="flex min-h-10 justify-center"/> : null
}

const AuthScreen = ({ onAuthenticated }) => {
  const [mode, setMode] = useState('login')
  const [form, setForm] = useState({ email: '', password: '', code: '' })
  const [googleClientId, setGoogleClientId] = useState('')
  const [codeSent, setCodeSent] = useState(false)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')
  const [loading, setLoading] = useState(false)

  useEffect(() => {
    fetch('/api/auth/config').then(response => response.json()).then(payload => setGoogleClientId(payload.googleClientId || '')).catch(() => {})
  }, [])

  const showError = useCallback(value => {
    setMessage('')
    setError(value)
  }, [])

  const changeMode = value => {
    setMode(value)
    setError('')
    setMessage('')
    setCodeSent(false)
    setForm(previous => ({ ...previous, password: '', code: '' }))
  }

  const submit = async event => {
    event.preventDefault()
    setLoading(true)
    setError('')
    setMessage('')
    try {
      const endpoint = mode === 'forgot' ? '/api/auth/password/reset' : `/api/auth/${mode}`
      const response = await fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(form)
      })
      const payload = await response.json()
      if (!response.ok) throw new Error(payload.message)
      if (mode === 'forgot') {
        changeMode('login')
        setMessage('Contraseña actualizada. Ya puedes iniciar sesión.')
      } else {
        onAuthenticated(payload.user)
      }
    } catch (submitError) {
      setError(submitError.message || 'No se pudo completar el acceso.')
    } finally {
      setLoading(false)
    }
  }

  const sendCode = async () => {
    setLoading(true)
    setError('')
    setMessage('')
    try {
      const endpoint = mode === 'forgot' ? '/api/auth/password/send-code' : '/api/auth/send-code'
      const response = await fetch(endpoint, {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ email: form.email })
      })
      const payload = await response.json()
      if (!response.ok) throw new Error(payload.message)
      setCodeSent(true)
      setMessage(mode === 'forgot' ? 'Si el correo está registrado, recibirás un código.' : 'Código enviado. Revisa tu correo.')
    } catch (sendError) {
      setError(sendError.message)
    } finally {
      setLoading(false)
    }
  }

  return (
    <main className="min-h-screen grid place-items-center bg-slate-950 p-4">
      <form onSubmit={submit} className="w-full max-w-md rounded-3xl bg-white p-8 shadow-2xl">
        <div className="mb-7 flex items-center gap-3 text-[#e31837]">
          <ShieldCheck size={38} />
          <div><h1 className="text-2xl font-black">SHALOM</h1><p className="text-xs font-bold uppercase tracking-widest text-slate-500">Declaración Jurada</p></div>
        </div>
        {mode !== 'forgot' && <div className="mb-6 grid grid-cols-2 rounded-xl bg-slate-100 p-1">
          {['login', 'register'].map(value => <button key={value} type="button" onClick={() => changeMode(value)} className={`rounded-lg py-2 text-sm font-bold ${mode === value ? 'bg-white text-red-600 shadow' : 'text-slate-500'}`}>{value === 'login' ? 'Ingresar' : 'Registrarme'}</button>)}
        </div>}
        {mode === 'forgot' && <div className="mb-5"><button type="button" onClick={() => changeMode('login')} className="text-sm font-bold text-red-600">← Volver al ingreso</button><h2 className="mt-3 text-xl font-black text-slate-900">Recuperar contraseña</h2></div>}
        <div className="space-y-4">
          <input type="email" required autoComplete="email" placeholder="Correo electrónico" value={form.email} onChange={event => setForm(previous => ({ ...previous, email: event.target.value }))} className={fieldClass}/>
          <input type="password" required minLength={8} autoComplete={mode === 'login' ? 'current-password' : 'new-password'} placeholder={mode === 'forgot' ? 'Nueva contraseña' : 'Contraseña (mínimo 8 caracteres)'} value={form.password} onChange={event => setForm(previous => ({ ...previous, password: event.target.value }))} className={fieldClass}/>
          {(mode === 'register' || mode === 'forgot') && <div className="flex gap-2"><input required maxLength={6} inputMode="numeric" placeholder="Código de correo" value={form.code} onChange={event => setForm(previous => ({ ...previous, code: event.target.value.replace(/\D/g, '') }))} className={`min-w-0 flex-1 ${fieldClass}`}/><button type="button" disabled={loading || !form.email} onClick={sendCode} className="rounded-xl bg-slate-900 px-3 text-xs font-bold text-white disabled:opacity-50">{codeSent ? 'REENVIAR' : 'ENVIAR CÓDIGO'}</button></div>}
        </div>
        {mode === 'login' && <button type="button" onClick={() => changeMode('forgot')} className="mt-3 text-sm font-bold text-red-600">Olvidé mi contraseña</button>}
        {error && <p className="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700" role="alert">{error}</p>}
        {message && <p className="mt-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-700" role="status">{message}</p>}
        <button disabled={loading} className="mt-6 w-full rounded-xl bg-[#e31837] py-3 font-black text-white disabled:opacity-60">{loading ? 'PROCESANDO...' : mode === 'login' ? 'INICIAR SESIÓN' : mode === 'register' ? 'CREAR CUENTA' : 'CAMBIAR CONTRASEÑA'}</button>
        {mode !== 'forgot' && googleClientId && <><div className="my-5 flex items-center gap-3 text-xs text-slate-400"><span className="h-px flex-1 bg-slate-200"/><span>O CONTINÚA CON</span><span className="h-px flex-1 bg-slate-200"/></div><GoogleSignIn clientId={googleClientId} onAuthenticated={onAuthenticated} onError={showError}/></>}
      </form>
    </main>
  )
}

export default AuthScreen
