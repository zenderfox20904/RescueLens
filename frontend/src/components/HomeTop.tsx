import { useAuth } from '../lib/auth'
import { NearbyHelp } from './NearbyHelp'
import { lazy, Suspense, useEffect, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { CATS, SEVS, fmtNumber, timeAgo, useI18n } from '../lib/i18n'
import { type MapPoint, ttl } from '../lib/types'
import { EmergencyBanner } from './Emergency'
import { Sk } from './Skeleton'
import { Sparkles } from 'lucide-react'

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
  const strip = useRef<HTMLDivElement>(null)
  const [me, setMe] = useState<{ lat: number; lng: number } | null>(null)
  // Location stays on the device; it is only used to sort alerts by distance.
  useEffect(() => { navigator.geolocation?.getCurrentPosition((p) => setMe({ lat: p.coords.latitude, lng: p.coords.longitude }), () => {}, { maximumAge: 300000, timeout: 8000 }) }, [])
  const { user } = useAuth()
  const center = user?.alert_lat != null && user?.alert_lng != null ? { lat: user.alert_lat, lng: user.alert_lng } : me
  const radius = user?.alert_radius_km ?? 10
  const active = (points ?? []).filter((p) => !p.is_stale && !['resolved', 'expired'].includes(p.status))
  const alerts = active
    .map((p) => ({ ...p, d: me ? km(me, p) : null }))
    .filter((p) => p.d === null || p.d <= Math.max(radius, 25))
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
          <span className="hero-ai-icon" aria-hidden><Sparkles size={20} /></span>
          <span><b>{t('ui.ai_auto_detect')}</b><small>{t('ui.ai_auto_detect_hint')}</small></span>
        </Link>
      </div>
      <EmergencyBanner />
      <div className="card hero-alerts">
        <div className="row" style={{ justifyContent: 'space-between' }}>
          <h3 className="h3"><span className="live-dot" /> {t('ui.nearby_live_alerts')}</h3>
          <Link to="/map" className="small link">{t('ui.open_live_map')}</Link>
        </div>
        <div className="alert-wrap">
          {!loading && alerts.length > 2 && <button className="alert-arrow prev" aria-label={t('ui.previous')} onClick={() => strip.current?.scrollBy({ left: -strip.current.clientWidth * (document.dir === 'rtl' ? -0.9 : 0.9), behavior: 'smooth' })}>‹</button>}
          <div className="alert-grid" ref={strip} data-testid="alert-grid">
            {loading ? Array.from({ length: 4 }).map((_, i) => <Sk key={i} h={76} r={12} />)
              : alerts.length === 0 ? <div className="muted small" style={{ padding: 8 }}>{t('ui.no_active_alerts_nearby')}</div>
              : alerts.map((a) => (
                <Link key={a.id} to={`/incidents/${a.id}`} className="alert-card fade-in" title={ttl(a)}>
                  <span className="alert-ic" aria-hidden>{CATS[a.category]?.icon}</span>
                  <span className="alert-tx">
                    <b>{ttl(a)}</b>
                    <span className="alert-meta">
                      <span className={'sev-pill sev-pill-' + a.severity}><i aria-hidden />{SEVS[a.severity]?.[lang] ?? a.severity}</span>
                      <small>{a.d !== null ? `${fmtNumber(+a.d.toFixed(1), lang)} km · ` : ''}{timeAgo(a.created_at, lang)}</small>
                    </span>
                  </span>
                </Link>
              ))}
          </div>
          {!loading && alerts.length > 2 && <button className="alert-arrow next" aria-label={t('ui.next')} onClick={() => strip.current?.scrollBy({ left: strip.current.clientWidth * (document.dir === 'rtl' ? -0.9 : 0.9), behavior: 'smooth' })}>›</button>}
        </div>
      </div>
      <div className="card hero-map">
        {loading ? <Sk w="100%" h={220} r={8} /> : (
          <Suspense fallback={<Sk w="100%" h={220} r={8} />}><div style={{ height: 220 }}><LeafletIncidentMap points={active} /></div></Suspense>
        )}
      </div>
      {center && <NearbyHelp lat={center.lat} lng={center.lng} radiusKm={radius} compact />}
    </section>
  )
}
