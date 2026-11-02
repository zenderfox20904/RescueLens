import { lazy, Suspense, useState } from 'react'
import { fmtNumber, useI18n } from '../../lib/i18n'
import { IPin, IMap } from '../Icons'
import { Sk } from '../Skeleton'

export type PickedLocation = { lat: number; lng: number; address: string | null; area_name: string | null; accuracy_m?: number | null; source?: 'gps' | 'map' }
const GMAPS_KEY = (import.meta.env.VITE_GOOGLE_MAPS_API_KEY as string | undefined) || ''
const GooglePick = lazy(() => import('./GooglePickMap'))
const OsmPick = lazy(() => import('./OsmPickMap'))

/**
 * Two ways to set an incident location:
 * "Use my location" (device geolocation with permission handling and accuracy) or
 * "Pick on map" (draggable pin, click to place, address search, reverse geocoded address).
 * The exact point is sent to the server only; the public feed shows a rounded location and the area name.
 */
export function LocationPicker({ value, onChange }: { value: PickedLocation | null; onChange: (v: PickedLocation) => void }) {
  const { t, lang } = useI18n()
  const [mode, setMode] = useState<'gps' | 'map' | null>(value?.source ?? null)
  const [gps, setGps] = useState<'idle' | 'asking' | 'denied' | 'unavailable' | 'ok'>('idle')
  const [mapFailed, setMapFailed] = useState(false)
  const locate = () => {
    setMode('gps')
    if (!('geolocation' in navigator)) { setGps('unavailable'); return }
    setGps('asking')
    navigator.geolocation.getCurrentPosition(
      async (p) => {
        setGps('ok')
        const v: PickedLocation = { lat: +p.coords.latitude.toFixed(6), lng: +p.coords.longitude.toFixed(6), accuracy_m: Math.round(p.coords.accuracy), address: null, area_name: null, source: 'gps' }
        onChange(v)
        const r = await reverseOsm(v.lat, v.lng, lang).catch(() => null)
        if (r) onChange({ ...v, ...r })
      },
      (e) => setGps(e.code === e.PERMISSION_DENIED ? 'denied' : 'unavailable'),
      { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
    )
  }
  return (
    <div className="locpick" data-testid="location-picker">
      <div className="locpick-tabs" role="tablist">
        <button type="button" role="tab" aria-selected={mode === 'gps'} className={'locpick-tab' + (mode === 'gps' ? ' on' : '')} onClick={locate} data-testid="use-my-location"><IPin size={18} /> {t('useMyLocation')}</button>
        <button type="button" role="tab" aria-selected={mode === 'map'} className={'locpick-tab' + (mode === 'map' ? ' on' : '')} onClick={() => setMode('map')} data-testid="pick-on-map"><IMap size={18} /> {t('ui.pick_on_map')}</button>
      </div>
      {mode === 'gps' && (
        <div className="locpick-status small" role="status">
          {gps === 'asking' && <><span className="spinner" /> {t('ui.locating')}</>}
          {gps === 'denied' && <span>{t('ui.location_permission_denied')} <button type="button" className="link-btn" onClick={() => setMode('map')}>{t('ui.pick_on_map')}</button></span>}
          {gps === 'unavailable' && <span>{t('ui.location_unavailable_enter_it_manually')} <button type="button" className="link-btn" onClick={() => setMode('map')}>{t('ui.pick_on_map')}</button></span>}
          {gps === 'ok' && value?.accuracy_m != null && <span className={'acc ' + (value.accuracy_m <= 50 ? 'good' : value.accuracy_m <= 500 ? 'ok' : 'poor')}>{t('ui.accuracy_about', { m: fmtNumber(value.accuracy_m, lang) })}</span>}
        </div>
      )}
      {mode === 'map' && (
        <Suspense fallback={<Sk h={300} r={10} />}>
          {GMAPS_KEY && !mapFailed
            ? <GooglePick apiKey={GMAPS_KEY} value={value} onChange={onChange} onError={() => setMapFailed(true)} />
            : <OsmPick value={value} onChange={onChange} />}
        </Suspense>
      )}
      {value && (
        <div className="locpick-result" data-testid="picked-location">
          <IPin size={16} />
          <div className="grow">
            <div>{value.address || value.area_name || t('ui.address_unknown')}</div>
            <div className="small muted">{fmtNumber(value.lat, lang, 5)}, {fmtNumber(value.lng, lang, 5)}{value.accuracy_m != null ? ` · ±${fmtNumber(value.accuracy_m, lang)} m` : ''}</div>
          </div>
        </div>
      )}
      <div className="small muted">{t('ui.only_an_approximate_location_about')}</div>
    </div>
  )
}

/** Reverse geocode through OpenStreetMap Nominatim (used for GPS results and the no key fallback). */
export async function reverseOsm(lat: number, lng: number, lang: string): Promise<{ address: string | null; area_name: string | null }> {
  const r = await fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}&zoom=18&accept-language=${lang}`)
  if (!r.ok) throw new Error('reverse')
  const j = await r.json()
  const a = j.address ?? {}
  return { address: j.display_name ?? null, area_name: a.suburb || a.neighbourhood || a.city_district || a.town || a.city || a.village || a.county || null }
}
