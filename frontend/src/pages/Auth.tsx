import { ORG_ICON } from '../lib/icons'
import { useEffect, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { API, api, errMsg } from '../lib/api'
import { useAuth } from '../lib/auth'
import { fmtNumber, useI18n } from '../lib/i18n'
import { ORG_TYPES, type User } from '../lib/types'
import { LocationPicker, type PickedLocation } from '../components/location/LocationPicker'
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
        <Link to="/forgot-password" className="small" style={{ textAlign: 'center' }}>{t('ui.forgot_password')}</Link>
        <div className="divider" />
        <SocialButtons />
        <div className="divider" />
        <Link to="/register" className="btn" style={{ background: 'var(--success)', color: '#fff', justifySelf: 'center', padding: '12px 16px' }}>{t('register')}</Link>
        <div className="small muted" style={{ textAlign: 'center' }}>{t('ui.demo')}: rina@rescuelens.test, <code>password</code></div>
      </form>
    </AuthFrame>
  )
}

export function Register() {
  const { t, lang } = useI18n()
  const { setSession } = useAuth()
  const nav = useNavigate()
  const [step, setStep] = useState(0)
  const [type, setType] = useState<string>('citizen')
  const [f, setF] = useState({ name: '', email: '', password: '' })
  const [o, setO] = useState({ name: '', phone: '', coverage_km: 10, service_hours: '24/7' })
  const [loc, setLoc] = useState<PickedLocation | null>(null)
  const [err, setErr] = useState<string | null>(null); const [busy, setBusy] = useState(false)
  const isOrg = type !== 'citizen'
  const emailOk = /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(f.email)
  const pwOk = f.password.length >= 8 && /[a-zA-Z]/.test(f.password) && /[0-9]/.test(f.password)
  const accountOk = f.name.trim().length > 1 && emailOk && pwOk
  const orgOk = o.name.trim().length > 1 && /^[0-9+ ()]{3,30}$/.test(o.phone) && !!loc
  const last = isOrg ? 2 : 1
  const submit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (step < last) { setStep(step + 1); return }
    setBusy(true); setErr(null)
    try {
      const body: Record<string, unknown> = { ...f, locale: lang, account_type: type }
      if (isOrg && loc) body.organization = { ...o, lat: loc.lat, lng: loc.lng, address: loc.address }
      const r = await api<{ user: User; token: string }>('/auth/register', { body })
      setSession(r.user, r.token); nav(isOrg ? '/responder' : '/feed')
    } catch (e) { setErr(errMsg(e)) } finally { setBusy(false) }
  }
  const types = ['citizen', ...ORG_TYPES]
  return (
    <AuthFrame>
      <form onSubmit={submit} style={{ display: 'grid', gap: 12 }} noValidate>
        <div><h2 style={{ margin: 0, fontSize: 28 }}>{t('ui.create_account')}</h2><div className="muted">{t('ui.step_of', { n: step + 1, total: last + 1 })}</div></div>
        <div className="steps" aria-hidden>{Array.from({ length: last + 1 }).map((_, k) => <span key={k} className={k <= step ? 'on' : ''} />)}</div>
        {step === 0 && <>
          <b>{t('ui.choose_account_type')}</b>
          <div className="acct-grid" role="radiogroup">
            {types.map((x) => (
              <button type="button" key={x} role="radio" aria-checked={type === x} className={'acct' + (type === x ? ' on' : '')} onClick={() => setType(x)} data-testid={`acct-${x}`}>
                <span aria-hidden>{ORG_ICON[x]}</span><b>{t(x === 'citizen' ? 'ui.org_citizen' : `ui.org_${x}`)}</b>
              </button>))}
          </div>
          {isOrg && <div className="small muted">{t('ui.org_review_note')}</div>}
        </>}
        {step === 1 && <>
          <input className="input" required maxLength={80} placeholder={t(isOrg ? 'ui.contact_person' : 'ui.name')} value={f.name} onChange={(e) => setF({ ...f, name: e.target.value })} />
          <input className={'input' + (f.email && !emailOk ? ' invalid' : '')} type="email" required placeholder={t('ui.email')} value={f.email} onChange={(e) => setF({ ...f, email: e.target.value })} />
          {f.email && !emailOk && <div className="small field-err">{t('ui.invalid_email')}</div>}
          <input className={'input' + (f.password && !pwOk ? ' invalid' : '')} type="password" required minLength={8} autoComplete="new-password" placeholder={t('ui.password_8_with_letters_and')} value={f.password} onChange={(e) => setF({ ...f, password: e.target.value })} />
          {f.password && !pwOk && <div className="small field-err">{t('ui.password_rule')}</div>}
        </>}
        {step === 2 && <>
          <input className="input" required maxLength={160} placeholder={t('ui.org_name')} value={o.name} onChange={(e) => setO({ ...o, name: e.target.value })} />
          <input className="input" required inputMode="tel" placeholder={t('ui.phone')} value={o.phone} onChange={(e) => setO({ ...o, phone: e.target.value })} />
          <LocationPicker value={loc} onChange={setLoc} />
          <label className="small">{t('ui.coverage_radius')}
            <select className="input" value={o.coverage_km} onChange={(e) => setO({ ...o, coverage_km: Number(e.target.value) })}>
              {[1, 5, 10, 25, 50].map((k) => <option key={k} value={k}>{t('ui.within_km', { km: fmtNumber(k, lang) })}</option>)}
            </select></label>
          <input className="input" maxLength={80} placeholder={t('ui.service_hours')} value={o.service_hours} onChange={(e) => setO({ ...o, service_hours: e.target.value })} />
        </>}
        {err && <div className="small" role="alert" style={{ color: 'var(--danger)' }}>{err}</div>}
        {step === last && <div className="small muted">{t('ui.signup_terms')}</div>}
        <div className="row" style={{ justifyContent: 'space-between' }}>
          {step > 0 ? <button type="button" className="btn" onClick={() => setStep(step - 1)}>{t('ui.back')}</button> : <span />}
          <button className="btn btn-primary" style={{ padding: '10px 28px' }} disabled={busy || (step === 1 && !accountOk) || (step === 2 && !orgOk)} data-testid="register-next">{step < last ? t('ui.next') : t('register')}</button>
        </div>
        {step === 0 && <SocialButtons />}
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
