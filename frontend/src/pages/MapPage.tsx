import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { MapContainer, TileLayer, CircleMarker, Popup } from 'react-leaflet'
import 'leaflet/dist/leaflet.css'
import { api } from '../lib/api'
import { CATS, timeAgo, useI18n } from '../lib/i18n'
import type { MapPoint } from '../lib/types'
import { ListSkeleton, Sk } from '../components/Skeleton'
import { StatusBadge } from '../components/PostCard'

const COLOR = { low: '#31A24C', moderate: '#F7B928', high: '#F5793B', critical: '#E41E3F' }
export default function MapPage() {
  const { lang } = useI18n()
  const q = useQuery({ queryKey: ['map', 168], queryFn: () => api<{ data: MapPoint[] }>('/incidents/map?hours=168'), refetchInterval: 15000 })
  return (
    <div className="main">
      <div className="card" style={{ padding: 8 }}>
        {q.isLoading ? <Sk h={480} r={8} /> : (
          <MapContainer center={[23.78, 90.39]} zoom={12} style={{ height: 480 }} scrollWheelZoom>
            <TileLayer attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors' url="https://tile.openstreetmap.org/{z}/{x}/{y}.png" />
            {q.data?.data.map((p) => (
              <CircleMarker key={p.id} center={[p.lat, p.lng]} radius={p.severity === 'critical' ? 14 : 10} pathOptions={{ color: '#fff', weight: 2, fillColor: COLOR[p.severity], fillOpacity: p.is_stale ? 0.4 : 0.9 }}>
                <Popup><b>{CATS[p.category]?.icon} {p.title}</b><br />{p.area_name} · {timeAgo(p.created_at, lang)}<br /><Link to={`/incidents/${p.id}`}>{lang === 'bn' ? 'বিস্তারিত' : 'Details'}</Link></Popup>
              </CircleMarker>))}
          </MapContainer>)}
        <div className="small muted" style={{ padding: 8 }}>{lang === 'bn' ? 'মার্কারগুলো আনুমানিক অবস্থান দেখায়। ম্যাপে কিছু না থাকলেও কোনো রাস্তা নিরাপদ বলে ধরে নেবেন না।' : 'Markers show approximate locations. An empty area does not mean a road is safe.'}</div>
      </div>
      <div className="card" style={{ padding: 8 }}>
        {q.isLoading ? <ListSkeleton rows={5} /> : q.data?.data.map((p) => (
          <Link key={p.id} to={`/incidents/${p.id}`} className="notif"><div className="sideicon" style={{ flex: 'none' }}>{CATS[p.category]?.icon}</div><div className="grow"><b>{p.title}</b><div className="small muted">{p.area_name} · {timeAgo(p.created_at, lang)} · 👁️ {p.confirm}</div></div><StatusBadge i={p} /></Link>
        ))}
      </div>
    </div>
  )
}
