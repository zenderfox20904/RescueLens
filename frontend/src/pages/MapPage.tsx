import { lazy, Suspense, useCallback, useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { api } from '../lib/api'
import { CATS, SEVS, timeAgo, useI18n } from '../lib/i18n'
import { type MapPoint, ttl } from '../lib/types'
import { ListSkeleton, Sk } from '../components/Skeleton'
import { StatusBadge } from '../components/PostCard'
import { MapErrorBoundary } from '../components/map/MapErrorBoundary'
import { Eye, Globe, LocateFixed, Flag, Flame } from 'lucide-react'
import { COUNTRY_CENTER, type MapFocus } from '../lib/countryCenter'

const GoogleIncidentMap = lazy(() => import('../components/map/GoogleIncidentMap'))
const LeafletIncidentMap = lazy(() => import('../components/map/LeafletIncidentMap'))
const GMAPS_KEY = (import.meta.env.VITE_GOOGLE_MAPS_API_KEY as string | undefined) || ''
const HOURS = [6, 24, 72, 168]

/** Live map: filter panel, clustered map, and a synced side list. Refreshes every 15 s. */
export default function MapPage() {
  const { t, lang, country } = useI18n()
  const [googleFailed, setGoogleFailed] = useState(false)
  const onError = useCallback(() => setGoogleFailed(true), [])
  const [hours, setHours] = useState(168)
  const [heat, setHeat] = useState(() => localStorage.getItem('rl_heat') === '1')
  const toggleHeat = () => setHeat((v) => { localStorage.setItem('rl_heat', v ? '0' : '1'); return !v })
  const [cats, setCats] = useState<string[]>([])
  const [sevs, setSevs] = useState<string[]>([])
  // Nearby: reports within 25 km of the device (location stays in the browser). World: every public report from the last 30 days.
  const [scope, setScope] = useState<'nearby' | 'country' | 'world'>('country')
  const [here, setHere] = useState<{ lat: number; lng: number } | null>(null)
  const [geoErr, setGeoErr] = useState(false)
  useEffect(() => { if (scope !== 'world' && !here && !geoErr) navigator.geolocation?.getCurrentPosition((g) => setHere({ lat: g.coords.latitude, lng: g.coords.longitude }), () => setGeoErr(true), { maximumAge: 300000 }) }, [scope, here, geoErr])
  const km = (a: { lat: number; lng: number }, b: { lat: number; lng: number }) => { const r = Math.PI / 180, x = Math.sin((b.lat - a.lat) * r / 2) ** 2 + Math.cos(a.lat * r) * Math.cos(b.lat * r) * Math.sin((b.lng - a.lng) * r / 2) ** 2; return 12742 * Math.asin(Math.sqrt(x)) }
  const effHours = scope === 'nearby' ? hours : 720
  // Opens on the active country: device location first, then the profile country, then the IP country from the server.
  const ipq = useQuery({ queryKey: ['geo-ip'], queryFn: () => api<{ country_code: string | null }>('/geo/ip'), enabled: scope === 'country' && geoErr && !COUNTRY_CENTER[country], staleTime: 3600000 })
  const cc = COUNTRY_CENTER[country] ? country : (ipq.data?.country_code ?? '')
  const focus: MapFocus = useMemo(() => scope === 'world' ? null : scope === 'nearby' ? (here ? { ...here, zoom: 11 } : null)
    : here ? { ...here, zoom: 7 } : COUNTRY_CENTER[cc] ? { lat: COUNTRY_CENTER[cc][0], lng: COUNTRY_CENTER[cc][1], zoom: COUNTRY_CENTER[cc][2] } : null, [scope, here, cc])
  const dark = document.documentElement.dataset.theme === 'dark'
  const q = useQuery({ queryKey: ['map', effHours], queryFn: () => api<{ data: MapPoint[] }>('/incidents/map?hours=' + effHours), refetchInterval: 15000 })
  const points = useMemo(() => (q.data?.data ?? []).filter((p) => (!cats.length || cats.includes(p.category)) && (!sevs.length || sevs.includes(p.severity)) && (scope === 'world' || !here || km(here, p) <= 25)), [q.data, cats, sevs, scope, here])
  const tog = (arr: string[], set: (v: string[]) => void, v: string) => set(arr.includes(v) ? arr.filter((x) => x !== v) : [...arr, v])
  const useGoogle = !!GMAPS_KEY && !googleFailed
  return (
    <div className="map-layout">
      <aside className="card map-filters" aria-label={t('ui.filters')}>
        <div className="row"><b className="grow" style={{ fontSize: 20 }}>{t('ui.filters')}</b>{(cats.length > 0 || sevs.length > 0) && <button className="btn btn-ghost small" onClick={() => { setCats([]); setSevs([]) }}>{t('ui.clear_filters')}</button>}</div>
        <div className="section-title">{t('ui.map_view')}</div>
        <div className="chips">{(['nearby', 'country', 'world'] as const).map((v) => { const I = { nearby: LocateFixed, country: Flag, world: Globe }[v]; return <button key={v} className={'chip' + (scope === v ? ' on' : '')} onClick={() => setScope(v)}><I size={14} aria-hidden /> {t('ui.map_' + v)}</button> })}</div>
        <div className="section-title">{t('ui.heatmap')}</div>
        <div className="chips"><button className={'chip' + (heat ? ' on' : '')} aria-pressed={heat} onClick={toggleHeat}><Flame size={14} aria-hidden /> {t('ui.hot_areas')}</button></div>
        {heat && <p className="small muted">{t('ui.heat_note')}</p>}
        {scope === 'nearby' && geoErr && <p className="small muted">{t('ui.map_location_off')}</p>}
        {scope === 'nearby' && <><div className="section-title">{t('ui.time_window')}</div>
        <div className="chips">{HOURS.map((h) => <button key={h} className={'chip' + (hours === h ? ' on' : '')} onClick={() => setHours(h)}>{t('ui.last_hours', { count: h })}</button>)}</div></>}
        <div className="section-title">{t('ui.severity')}</div>
        <div className="chips">{Object.keys(SEVS).map((s) => <button key={s} className={'chip sev-chip sev-' + s + (sevs.includes(s) ? ' on' : '')} onClick={() => tog(sevs, setSevs, s)}>{SEVS[s][lang]}</button>)}</div>
        <div className="section-title">{t('category')}</div>
        <div className="chips">{Object.entries(CATS).map(([k, v]) => <button key={k} className={'chip' + (cats.includes(k) ? ' on' : '')} onClick={() => tog(cats, setCats, k)}>{v.icon} {v[lang]}</button>)}</div>
      </aside>
      <div className="card map-canvas">
        {q.isLoading ? <Sk h="100%" r={8} /> : (
          <Suspense fallback={<Sk h="100%" r={8} />}>
            {useGoogle ? <MapErrorBoundary fallback={<LeafletIncidentMap points={points} focus={focus} dark={dark} heat={heat} />}><GoogleIncidentMap apiKey={GMAPS_KEY} points={points} dark={dark} onError={onError} focus={focus} heat={heat} /></MapErrorBoundary> : <LeafletIncidentMap points={points} focus={focus} dark={dark} heat={heat} />}
          </Suspense>)}
        <div className="small muted map-note"><span className="live-dot" /> {t('ui.live')} · {useGoogle ? 'Google Maps' : 'OpenStreetMap'} · {t('ui.markers_show_approximate_locations_an')}</div>
      </div>
      <aside className="card map-list" role="list" aria-label={t('ui.incident_list')}>
        <div className="row" style={{ padding: 8 }}><b className="grow" style={{ fontSize: 20 }}>{t('ui.incident_list')}</b><span className="badge">{points.length}</span></div>
        {q.isLoading ? <ListSkeleton rows={6} /> : !points.length ? <p className="muted" style={{ padding: 8 }}>{t('ui.empty')}</p> : points.map((p) => (
          <Link role="listitem" key={p.id} to={`/incidents/${p.id}`} className="notif fade-in"><div className={'sideicon sev-ring sev-' + p.severity} style={{ flex: 'none' }} aria-hidden>{CATS[p.category]?.icon}</div><div className="grow"><b>{ttl(p)}</b><div className="small muted">{p.area_name} · {timeAgo(p.created_at, lang)} · <Eye size={12} /> {p.confirm}</div></div><StatusBadge i={p} /></Link>))}
      </aside>
    </div>
  )
}
