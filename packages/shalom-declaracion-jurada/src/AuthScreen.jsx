import { useEffect, useState } from 'react'
import { ShieldCheck } from 'lucide-react'

const fieldClass = 'w-full rounded-xl border border-slate-200 p-3 text-slate-900 placeholder:text-slate-400'

const AuthScreen = ({ onAuthenticated }) => {
  const [form, setForm] = useState({ email: '', password: '' })
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)
  const [coderedUrl, setCoderedUrl] = useState('')

  useEffect(() => {
    fetch('/api/config').then(response => response.json()).then(payload => setCoderedUrl(payload.coderedUrl || '')).catch(() => {})
  }, [])

  const submit = async event => {
    event.preventDefault()
    setLoading(true)
    setError('')
    try {
      const response = await fetch('/api/auth/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(form)
      })
      const payload = await response.json()
      if (!response.ok) throw new Error(payload.message)
      onAuthenticated(payload.user)
    } catch (submitError) {
      setError(submitError.message || 'No se pudo iniciar sesión.')
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
        <div className="space-y-4">
          <input type="email" required autoComplete="email" placeholder="Correo electrónico" value={form.email} onChange={event => setForm(previous => ({ ...previous, email: event.target.value }))} className={fieldClass}/>
          <input type="password" required autoComplete="current-password" placeholder="Contraseña" value={form.password} onChange={event => setForm(previous => ({ ...previous, password: event.target.value }))} className={fieldClass}/>
        </div>
        {error && <p className="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700" role="alert">{error}</p>}
        <button disabled={loading} className="mt-6 w-full rounded-xl bg-[#e31837] py-3 font-black text-white disabled:opacity-60">{loading ? 'INGRESANDO...' : 'INICIAR SESIÓN'}</button>
        <p className="mt-5 text-center text-xs text-slate-400">
          Utiliza tu cuenta de CodeRED Platform.
          {coderedUrl && <> <a href={`${coderedUrl}/login`} target="_blank" rel="noopener noreferrer" className="font-bold text-red-600 hover:underline">¿Problemas para ingresar?</a></>}
        </p>
      </form>
    </main>
  )
}

export default AuthScreen
