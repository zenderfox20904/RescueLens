import { lazy, Suspense, useCallback, useState } from 'react'
import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { api } from '../lib/api'
import { CATS, timeAgo, useI18n } from '../lib/i18n'
import type { MapPoint } from '../lib/types'
import { ListSkeleton, Sk } from '../components/Skeleton'
import { StatusBadge } from '../components/PostCard'
import { MapErrorBoundary } from "../components/map/MapErrorBoundary"

const GoogleIncidentMap = lazy(() => import('../components/map/GoogleIncidentMap'))
const LeafletIncidentMap = lazy(() => import('../components/map/LeafletIncidentMap'))
const GMAPS_KEY = (import.meta.env.VITE_GOOGLE_MAPS_API_KEY as string | undefined) || ''

export default function MapPage() {
  const { t, lang } = useI18n()
  const [googleFailed, setGoogleFailed] = useState(false)
  const onError = useCallback(() => setGoogleFailed(true), [])
  const dark = document.documentElement.dataset.theme === 'dark'
  const q = useQuery({ queryKey: ['map', 168], queryFn: () => api<{ data: MapPoint[] }>('/incidents/map?hours=168'), refetchInterval: 15000 })
  const points = q.data?.data ?? []
  const useGoogle = !!GMAPS_KEY && !googleFailed
  return (
    <div className="main">
      <div className="card" style={{ padding: 8 }}>
        {q.isLoading ? <Sk h={480} r={8} /> : (
          <Suspense fallback={<Sk h={480} r={8} />}>
            {useGoogle ? <MapErrorBoundary fallback={<LeafletIncidentMap points={points} />}><GoogleIncidentMap apiKey={GMAPS_KEY} points={points} dark={dark} onError={onError} /></MapErrorBoundary> : <LeafletIncidentMap points={points} />}
          </Suspense>)}
        <div className="small muted" style={{ padding: 8 }}>
          {useGoogle ? 'Google Maps' : 'OpenStreetMap'} · {t('ui.markers_show_approximate_locations_an')}
        </div>
      </div>
      <div className="card" style={{ padding: 8 }} role="list" aria-label={t('ui.incident_list')}>
        {q.isLoading ? <ListSkeleton rows={5} /> : points.map((p) => (
          <Link role="listitem" key={p.id} to={`/incidents/${p.id}`} className="notif"><div className="sideicon" style={{ flex: 'none' }} aria-hidden>{CATS[p.category]?.icon}</div><div className="grow"><b>{p.title}</b><div className="small muted">{CATS[p.category]?.[lang]} · {p.area_name} · {timeAgo(p.created_at, lang)} · 👁️ {p.confirm}</div></div><StatusBadge i={p} /></Link>
        ))}
      </div>
    </div>
  )
}
