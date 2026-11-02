import { useEffect, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { API, api, errMsg } from '../lib/api'
import { useAuth } from '../lib/auth'
import { useI18n } from '../lib/i18n'
import type { User } from '../lib/types'
import { Wordmark } from '../components/Logo'
import { IFacebook, IGoogle } from '../components/Icons'

function SocialButtons() {
  const { t } = useI18n()
  const [msg, setMsg] = useState<string | null>(null)
  const go = async (p: 'facebook' | 'google') => {
    setMsg(null)
    // Probe first so a missing configuration shows a clear message instead of a broken redirect.
    const res = await fetch(`${API}/auth/${p}/redirect`, { redirect: 'manual', headers: { Accept: 'application/json' } }).catch(() => null)
    if (res && res.status === 503) { setMsg((await res.json()).message); return }
    window.location.href = `${API}/auth/${p}/redirect`
  }
  return (
    <div style={{ display: 'grid', gap: 8 }}>
      <button type="button" className="btn btn-primary" style={{ padding: 12 }} onClick={() => go('facebook')}><IFacebook /> {t('ui.continue_with_facebook')}</button>
      <button type="button" className="btn" style={{ padding: 12, background: 'var(--card)', border: '1px solid var(--divider)' }} onClick={() => go('google')}><IGoogle /> {t('ui.continue_with_google')}</button>
      {msg && <div className="small" role="alert" style={{ color: 'var(--danger)' }}>{msg}</div>}
    </div>
  )
}

function AuthFrame({ children }: { children: React.ReactNode }) {
  const { t } = useI18n()
  return (
    <div style={{ minHeight: '100vh', display: 'grid', placeItems: 'center', padding: 16 }}>
      <div style={{ display: 'flex', gap: 48, alignItems: 'center', flexWrap: 'wrap', justifyContent: 'center', maxWidth: 1000 }}>
        <div style={{ maxWidth: 500 }}>
          <Wordmark size={64} />
          <p style={{ fontSize: 26, lineHeight: 1.25, marginTop: 16 }}>{t('ui.snap_a_photo_ai_highlights')}</p>
        </div>
        <div className="card" style={{ width: 400, padding: 16 }}>{children}</div>
      </div>
    </div>
  )
}

export function Login() {
  const { t } = useI18n()
  const { setSession } = useAuth()
  const nav = useNavigate()
  const [email, setEmail] = useState(''); const [password, setPassword] = useState('')
  const [err, setErr] = useState<string | null>(null); const [busy, setBusy] = useState(false)
  const submit = async (e: React.FormEvent) => {
    e.preventDefault(); setBusy(true); setErr(null)
    try { const r = await api<{ user: User; token: string }>('/auth/login', { body: { email, password } }); setSession(r.user, r.token); nav('/feed') } catch (e) { setErr(errMsg(e)) } finally { setBusy(false) }
  }
  return (
    <AuthFrame>
      <form onSubmit={submit} style={{ display: 'grid', gap: 12 }}>
        <input className="input" type="email" required autoComplete="email" placeholder={t('ui.email')} value={email} onChange={(e) => setEmail(e.target.value)} />
        <input className="input" type="password" required autoComplete="current-password" placeholder={t('ui.password')} value={password} onChange={(e) => setPassword(e.target.value)} />
        {err && <div className="small" role="alert" style={{ color: 'var(--danger)' }}>{err}</div>}
        <button className="btn btn-primary" style={{ padding: 12, fontSize: 18 }} disabled={busy}>{t('login')}</button>
        <div className="divider" />
        <SocialButtons />
        <div className="divider" />
        <Link to="/register" className="btn" style={{ background: 'var(--success)', color: '#fff', justifySelf: 'center', padding: '12px 16px' }}>{t('register')}</Link>
        <div className="small muted" style={{ textAlign: 'center' }}>{t('ui.demo')}: rina@rescuelens.test / moderator@rescuelens.test, password: <code>password</code></div>
      </form>
    </AuthFrame>
  )
}

export function Register() {
  const { t, lang } = useI18n()
  const { setSession } = useAuth()
  const nav = useNavigate()
  const [f, setF] = useState({ name: '', email: '', password: '' })
  const [err, setErr] = useState<string | null>(null); const [busy, setBusy] = useState(false)
  const submit = async (e: React.FormEvent) => {
    e.preventDefault(); setBusy(true); setErr(null)
    try { const r = await api<{ user: User; token: string }>('/auth/register', { body: { ...f, locale: lang } }); setSession(r.user, r.token); nav('/feed') } catch (e) { setErr(errMsg(e)) } finally { setBusy(false) }
  }
  return (
    <AuthFrame>
      <form onSubmit={submit} style={{ display: 'grid', gap: 12 }}>
        <h2 style={{ margin: 0 }}>{t('register')}</h2>
        <input className="input" required maxLength={80} placeholder={t('ui.name')} value={f.name} onChange={(e) => setF({ ...f, name: e.target.value })} />
        <input className="input" type="email" required placeholder={t('ui.email')} value={f.email} onChange={(e) => setF({ ...f, email: e.target.value })} />
        <input className="input" type="password" required minLength={8} autoComplete="new-password" placeholder={t('ui.password_8_with_letters_and')} value={f.password} onChange={(e) => setF({ ...f, password: e.target.value })} />
        {err && <div className="small" role="alert" style={{ color: 'var(--danger)' }}>{err}</div>}
        <button className="btn btn-primary" style={{ padding: 12 }} disabled={busy}>{t('register')}</button>
        <SocialButtons />
        <Link to="/login" style={{ textAlign: 'center' }}>{t('login')}</Link>
      </form>
    </AuthFrame>
  )
}

export function AuthCallback() {
  const [sp] = useSearchParams()
  const { setSession } = useAuth()
  const nav = useNavigate()
  const { t } = useI18n()
  const [err, setErr] = useState<string | null>(sp.get('error'))
  useEffect(() => {
    const code = sp.get('code')
    if (!code) return
    api<{ user: User; token: string }>('/auth/social/exchange', { body: { code } }).then((r) => { setSession(r.user, r.token); nav('/feed', { replace: true }) }).catch((e) => setErr(errMsg(e)))
  }, [])
  return <AuthFrame>{err ? <div role="alert">{t('ui.login_failed')}: {err.replace(/_/g, ' ')}. <Link to="/login">{t('ui.back')}</Link></div> : <div>{t('ui.signing_in')}</div>}</AuthFrame>
}
