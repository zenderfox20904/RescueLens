import { useEffect, useState } from 'react'
import { MapContainer, TileLayer, Marker, useMap, useMapEvents } from 'react-leaflet'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { useI18n } from '../../lib/i18n'
import { reverseOsm, type PickedLocation } from './LocationPicker'

const DHAKA: [number, number] = [23.8103, 90.4125]
const pin = L.divIcon({ className: 'osm-pin', html: '<span></span>', iconSize: [28, 28], iconAnchor: [14, 28] })

function Clicks({ on }: { on: (lat: number, lng: number) => void }) { useMapEvents({ click: (e) => on(e.latlng.lat, e.latlng.lng) }); return null }
function Fly({ to }: { to: [number, number] | null }) { const m = useMap(); useEffect(() => { if (to) m.flyTo(to, 16) }, [to, m]); return null }

/** OpenStreetMap pick map used when no Google Maps key is configured or Google rejects the key. */
export default function OsmPickMap({ value, onChange }: { value: PickedLocation | null; onChange: (v: PickedLocation) => void }) {
  const { t, lang } = useI18n()
  const [q, setQ] = useState('')
  const [sugs, setSugs] = useState<{ lat: number; lng: number; label: string }[]>([])
  const [fly, setFly] = useState<[number, number] | null>(null)
  const pos: [number, number] = value ? [value.lat, value.lng] : DHAKA
  const place = async (lat: number, lng: number) => {
    const base: PickedLocation = { lat: +lat.toFixed(6), lng: +lng.toFixed(6), address: null, area_name: null, accuracy_m: null, source: 'map' }
    onChange(base)
    const r = await reverseOsm(lat, lng, lang).catch(() => null)
    if (r) onChange({ ...base, ...r })
  }
  useEffect(() => {
    if (q.trim().length < 3) { setSugs([]); return }
    const h = setTimeout(async () => {
      try {
        const r = await fetch(`https://nominatim.openstreetmap.org/search?format=jsonv2&limit=5&accept-language=${lang}&q=${encodeURIComponent(q)}`)
        setSugs((await r.json()).map((x: any) => ({ lat: +x.lat, lng: +x.lon, label: x.display_name })))
      } catch { setSugs([]) }
    }, 400)
    return () => clearTimeout(h)
  }, [q, lang])
  return (
    <>
      <div className="locpick-search">
        <input className="input" data-testid="place-search" placeholder={t('ui.search_address')} value={q} onChange={(e) => setQ(e.target.value)} />
        {sugs.length > 0 && <div className="card locpick-sugs" role="listbox">{sugs.map((s, i) => <button type="button" role="option" aria-selected={false} key={i} onClick={() => { setSugs([]); setQ(s.label.split(',')[0]); setFly([s.lat, s.lng]); place(s.lat, s.lng) }}><span>{s.label}</span></button>)}</div>}
      </div>
      <MapContainer center={pos} zoom={value ? 16 : 12} className="locpick-map" scrollWheelZoom>
        <TileLayer url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png" attribution="© OpenStreetMap" />
        <Clicks on={place} /><Fly to={fly} />
        <Marker position={pos} icon={pin} draggable eventHandlers={{ dragend: (e) => { const l = (e.target as L.Marker).getLatLng(); place(l.lat, l.lng) } }} />
      </MapContainer>
      <div className="small muted">{t('ui.click_or_drag_pin')}</div>
    </>
  )
}
