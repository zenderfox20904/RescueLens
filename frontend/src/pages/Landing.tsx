import { Link, Navigate } from 'react-router-dom'
import { useAuth } from '../lib/auth'
import { useI18n } from '../lib/i18n'
import { LangToggle } from '../components/Shell'
import { EmergencyBanner } from '../components/Emergency'
import { Wordmark } from '../components/Logo'
import { Camera, Sparkles, CircleCheck, MapPin } from 'lucide-react'

export default function Landing() {
  const { user } = useAuth()
  const { t } = useI18n()
  if (user) return <Navigate to="/feed" replace />
  const steps = t('ui.steps', { returnObjects: true }) as unknown as string[][]
  return (
    <div>
      <div style={{ background: 'linear-gradient(135deg, var(--grad-from), var(--grad-to))', color: '#fff', padding: '56px 16px' }}>
        <div style={{ maxWidth: 980, margin: '0 auto' }}>
          <div className="row"><div className="grow" style={{ filter: 'brightness(0) invert(1)' }}><Wordmark size={44} /></div>
            <LangToggle className="btn" /></div>
          <h1 style={{ fontSize: 44, lineHeight: 1.1, margin: '40px 0 12px' }}>{t('ui.see_an_emergency_let_your')}</h1>
          <p style={{ fontSize: 20, opacity: 0.92, maxWidth: 640 }}>{t('ui.ai_assisted_community_incident_reporting')}</p>
          <div className="row" style={{ marginTop: 24, flexWrap: 'wrap' }}>
            <Link to="/register" className="btn" style={{ padding: '12px 20px', fontSize: 17 }}>{t('ui.get_started')}</Link>
            <Link to="/feed" className="btn" style={{ padding: '12px 20px', fontSize: 17, background: 'transparent', color: '#fff', border: '1px solid #fff' }}>{t('ui.view_live_feed')}</Link>
          </div>
        </div>
      </div>
      <div style={{ maxWidth: 980, margin: '24px auto', padding: 16 }}>
        <EmergencyBanner />
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(200px,1fr))', gap: 12, marginTop: 16 }}>
          {steps.map(([, h, p], n) => <div key={h} className="card" style={{ padding: 16 }}><div style={{ color: 'var(--primary)' }}>{[<Camera size={30} />, <Sparkles size={30} />, <CircleCheck size={30} />, <MapPin size={30} />][n]}</div><b>{h}</b><p className="muted small">{p}</p></div>)}
        </div>
        <p className="small muted" style={{ marginTop: 16 }}>{t('ui.ai_output_is_a_suggestion')}</p>
      </div>
      <footer className="small muted" style={{ textAlign: 'center', padding: 24 }}>© 2026 RescueLens</footer>
    </div>
  )
}
