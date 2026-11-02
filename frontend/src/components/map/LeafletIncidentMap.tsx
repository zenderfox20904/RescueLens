import { Link } from 'react-router-dom'
import { useEffect, useState } from 'react'
import type { MapFocus } from '../../lib/countryCenter'
import { MapContainer, TileLayer, CircleMarker, Popup, Tooltip, useMapEvents, useMap } from 'react-leaflet'
import 'leaflet/dist/leaflet.css'
import { timeAgo, useI18n } from '../../lib/i18n'
import { type MapPoint, ttl } from '../../lib/types'

const SEV = { low: '#31A24C', moderate: '#F7B928', high: '#F5793B', critical: '#E41E3F' }
/** OpenStreetMap fallback used when no Google Maps key is configured or the key fails. */
function Focus({ focus, points }: { focus: MapFocus; points: MapPoint[] }) {
  const m = useMap()
  useEffect(() => { if (focus) m.setView([focus.lat, focus.lng], focus.zoom); else if (points.length) m.fitBounds(points.map((p) => [p.lat, p.lng] as [number, number]), { padding: [40, 40] }) }, [focus, points.length]) // eslint-disable-line react-hooks/exhaustive-deps
  return null
}
export default function LeafletIncidentMap({ points, focus = null }: { points: MapPoint[]; focus?: MapFocus }) {
  const { t, lang } = useI18n()
  const [zoom, setZoom] = useState(12)
  const { singles, clusters } = cluster(points, zoom)
  const center: [number, number] = points.length ? [points[0].lat, points[0].lng] : [23.78, 90.39]
  return (
    <MapContainer center={center} zoom={12} style={{ height: '100%', minHeight: 420, borderRadius: 8 }} scrollWheelZoom>
      <Focus focus={focus} points={points} />
      <ZoomWatch onZoom={setZoom} />
      {clusters.map((c) => (
        <CircleMarker key={c.key} center={[c.lat, c.lng]} radius={14 + Math.min(16, c.n * 2)} pathOptions={{ color: '#fff', weight: 3, fillColor: SEV[c.worst], fillOpacity: 0.85 }}>
          <Tooltip permanent direction="center" className="cluster-label">{c.n}</Tooltip>
        </CircleMarker>))}
      <TileLayer attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors' url="https://tile.openstreetmap.org/{z}/{x}/{y}.png" />
      {singles.map((p) => (
        <CircleMarker key={p.id} center={[p.lat, p.lng]} radius={p.severity === 'critical' ? 14 : 10} className={p.severity === 'critical' ? 'pulse' : undefined} pathOptions={{ color: '#fff', weight: 2, fillColor: SEV[p.severity], fillOpacity: p.is_stale ? 0.4 : 0.9 }}>
          <Popup><b>{ttl(p)}</b><br />{p.area_name} · {timeAgo(p.created_at, lang)}<br /><Link to={`/incidents/${p.id}`}>{t('ui.details')}</Link></Popup>
        </CircleMarker>))}
    </MapContainer>
  )
}

function ZoomWatch({ onZoom }: { onZoom: (z: number) => void }) {
  const m = useMapEvents({ zoomend: () => onZoom(m.getZoom()) })
  return null
}

const RANK = { low: 0, moderate: 1, high: 2, critical: 3 } as const
/** Simple grid clustering: points sharing a cell at the current zoom collapse into one bubble colored by the worst severity. */
function cluster(points: MapPoint[], zoom: number) {
  const cell = 0.6 / Math.pow(2, zoom - 8)
  const cells = new Map<string, MapPoint[]>()
  for (const p of points) { const k = Math.floor(p.lat / cell) + ':' + Math.floor(p.lng / cell); cells.set(k, [...(cells.get(k) ?? []), p]) }
  const singles: MapPoint[] = []; const clusters: { key: string; lat: number; lng: number; n: number; worst: MapPoint['severity'] }[] = []
  cells.forEach((ps, key) => {
    if (ps.length === 1 || zoom >= 16) { singles.push(...ps); return }
    const worst = ps.reduce((w, p) => (RANK[p.severity] > RANK[w] ? p.severity : w), ps[0].severity)
    clusters.push({ key, n: ps.length, worst, lat: ps.reduce((a, p) => a + p.lat, 0) / ps.length, lng: ps.reduce((a, p) => a + p.lng, 0) / ps.length })
  })
  return { singles, clusters }
}
