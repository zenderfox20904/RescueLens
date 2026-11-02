import { Link } from 'react-router-dom'
import { MapContainer, TileLayer, CircleMarker, Popup } from 'react-leaflet'
import 'leaflet/dist/leaflet.css'
import { CATS, timeAgo, useI18n } from '../../lib/i18n'
import type { MapPoint } from '../../lib/types'

const SEV = { low: '#31A24C', moderate: '#F7B928', high: '#F5793B', critical: '#E41E3F' }
/** OpenStreetMap fallback used when no Google Maps key is configured or the key fails. */
export default function LeafletIncidentMap({ points }: { points: MapPoint[] }) {
  const { t, lang } = useI18n()
  return (
    <MapContainer center={[23.78, 90.39]} zoom={12} style={{ height: 480, borderRadius: 8 }} scrollWheelZoom>
      <TileLayer attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors' url="https://tile.openstreetmap.org/{z}/{x}/{y}.png" />
      {points.map((p) => (
        <CircleMarker key={p.id} center={[p.lat, p.lng]} radius={p.severity === 'critical' ? 14 : 10} pathOptions={{ color: '#fff', weight: 2, fillColor: SEV[p.severity], fillOpacity: p.is_stale ? 0.4 : 0.9 }}>
          <Popup><b>{CATS[p.category]?.icon} {p.title}</b><br />{p.area_name} · {timeAgo(p.created_at, lang)}<br /><Link to={`/incidents/${p.id}`}>{t('ui.details')}</Link></Popup>
        </CircleMarker>))}
    </MapContainer>
  )
}
