import { lazy, Suspense, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { CATS, SEVS, timeAgo, useI18n } from '../lib/i18n'
import type { MapPoint } from '../lib/types'
import { EmergencyBanner } from './Emergency'
import { Sk } from './Skeleton'

const LeafletIncidentMap = lazy(() => import('./map/LeafletIncidentMap'))
const RANK: Record<string, number> = { critical: 0, high: 1, moderate: 2, low: 3 }

function km(a: { lat: number; lng: number }, b: { lat: number; lng: number }) {
  const r = (d: number) => (d * Math.PI) / 180
  const x = Math.sin(r(b.lat - a.lat) / 2) ** 2 + Math.cos(r(a.lat)) * Math.cos(r(b.lat)) * Math.sin(r(b.lng - a.lng) / 2) ** 2
  return 6371 * 2 * Math.asin(Math.sqrt(x))
}

/** Main features first: one tap report, AI auto detect, nearby live alerts, verified numbers, live map preview. */
export function HomeTop({ points, loading }: { points?: MapPoint[]; loading: boolean }) {
  const { t, lang } = useI18n()
  const [me, setMe] = useState<{ lat: number; lng: number } | null>(null)
  // Location stays on the device; it is only used to sort alerts by distance.
  useEffect(() => { navigator.geolocation?.getCurrentPosition((p) => setMe({ lat: p.coords.latitude, lng: p.coords.longitude }), () => {}, { maximumAge: 300000, timeout: 8000 }) }, [])
  const active = (points ?? []).filter((p) => !p.is_stale && !['resolved', 'expired'].includes(p.status))
  const alerts = active
    .map((p) => ({ ...p, d: me ? km(me, p) : null }))
    .filter((p) => p.d === null || p.d <= 25)
    .sort((a, b) => (RANK[a.severity] - RANK[b.severity]) || ((a.d ?? 0) - (b.d ?? 0)))
    .slice(0, 10)
  return (
    <section className="hometop" aria-label={t('ui.main_features')}>
      <div className="hero-actions">
        <Link to="/report" className="hero-sos" data-testid="report-emergency">
          <span className="hero-sos-dot" aria-hidden />
          <span><b>{t('ui.report_emergency')}</b><small>{t('ui.one_tap_report_hint')}</small></span>
        </Link>
        <Link to="/report?auto=1" className="hero-ai card">
          <span className="hero-ai-icon" aria-hidden>✨</span>
          <span><b>{t('ui.ai_auto_detect')}</b><small>{t('ui.ai_auto_detect_hint')}</small></span>
        </Link>
      </div>
      <EmergencyBanner />
      <div className="card hero-alerts">
        <div className="row" style={{ justifyContent: 'space-between' }}>
          <h3 className="h3"><span className="live-dot" /> {t('ui.nearby_live_alerts')}</h3>
          <Link to="/map" className="small link">{t('ui.open_live_map')}</Link>
        </div>
        <div className="alert-strip">
          {loading ? Array.from({ length: 4 }).map((_, i) => <Sk key={i} w={210} h={64} r={10} style={{ flex: 'none' }} />)
            : alerts.length === 0 ? <div className="muted small" style={{ padding: 8 }}>{t('ui.no_active_alerts_nearby')}</div>
            : alerts.map((a) => (
              <Link key={a.id} to={`/incidents/${a.id}`} className={'alert-chip sev-' + a.severity}>
                <span className="alert-ic">{CATS[a.category]?.icon}</span>
                <span className="alert-tx"><b>{a.title.replace('[DEMO] ', '')}</b>
                  <small>{SEVS[a.severity]?.[lang] ?? a.severity} · {a.d !== null ? `${a.d.toFixed(1)} km · ` : ''}{timeAgo(a.created_at, lang)}</small></span>
              </Link>
            ))}
        </div>
      </div>
      <div className="card hero-map">
        {loading ? <Sk w="100%" h={220} r={8} /> : (
          <Suspense fallback={<Sk w="100%" h={220} r={8} />}><div style={{ height: 220 }}><LeafletIncidentMap points={active} /></div></Suspense>
        )}
      </div>
    </section>
  )
}
