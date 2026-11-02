/// <reference types="google.maps" />
import { useEffect, useRef, useState } from 'react'
import { APIProvider, Map, Marker, useMap, useMapsLibrary } from '@vis.gl/react-google-maps'
import { useI18n } from '../../lib/i18n'
import type { PickedLocation } from './LocationPicker'

const DHAKA = { lat: 23.8103, lng: 90.4125 }
type Sug = { id: string; main: string; secondary: string; pred: any }

function areaFrom(c: google.maps.GeocoderAddressComponent[] | undefined) {
  const f = (t: string) => c?.find((x) => x.types.includes(t))?.long_name
  return f('sublocality_level_1') || f('sublocality') || f('neighborhood') || f('locality') || f('administrative_area_level_2') || null
}

function Inner({ value, onChange }: { value: PickedLocation | null; onChange: (v: PickedLocation) => void }) {
  const { t, lang } = useI18n()
  const map = useMap()
  const geocoding = useMapsLibrary('geocoding')
  const places = useMapsLibrary('places')
  const [q, setQ] = useState('')
  const [sugs, setSugs] = useState<Sug[]>([])
  const [busy, setBusy] = useState(false)
  const token = useRef<any>(null)
  const pos = value ? { lat: value.lat, lng: value.lng } : DHAKA

  const reverse = async (lat: number, lng: number) => {
    const base: PickedLocation = { lat: +lat.toFixed(6), lng: +lng.toFixed(6), address: null, area_name: null, accuracy_m: null, source: 'map' }
    onChange(base)
    if (!geocoding) return
    try {
      const r = await new geocoding.Geocoder().geocode({ location: { lat, lng }, language: lang })
      const top = r.results[0]
      onChange({ ...base, address: top?.formatted_address ?? null, area_name: areaFrom(top?.address_components) })
    } catch { /* address stays empty; coordinates are still saved */ }
  }

  // Places autocomplete (Places API New): suggestions as the user types, debounced.
  useEffect(() => {
    if (!places || q.trim().length < 3) { setSugs([]); return }
    const h = setTimeout(async () => {
      try {
        const P: any = places
        token.current ??= new P.AutocompleteSessionToken()
        const { suggestions } = await P.AutocompleteSuggestion.fetchAutocompleteSuggestions({ input: q, sessionToken: token.current, language: lang, locationBias: map?.getCenter() ?? undefined })
        setSugs(suggestions.filter((s: any) => s.placePrediction).slice(0, 5).map((s: any) => ({ id: s.placePrediction.placeId, main: s.placePrediction.mainText?.text ?? s.placePrediction.text.text, secondary: s.placePrediction.secondaryText?.text ?? '', pred: s.placePrediction })))
      } catch { setSugs([]) }
    }, 250)
    return () => clearTimeout(h)
  }, [q, places, lang, map])

  const choose = async (s: Sug) => {
    setBusy(true); setSugs([]); setQ(s.main)
    try {
      const place = s.pred.toPlace()
      await place.fetchFields({ fields: ['location', 'formattedAddress', 'addressComponents'] })
      token.current = null
      const loc = place.location
      if (!loc) return
      map?.panTo(loc); map?.setZoom(17)
      const comps = (place.addressComponents ?? []).map((c: any) => ({ long_name: c.longText, short_name: c.shortText, types: c.types }))
      onChange({ lat: +loc.lat().toFixed(6), lng: +loc.lng().toFixed(6), address: place.formattedAddress ?? s.main, area_name: areaFrom(comps), accuracy_m: null, source: 'map' })
    } finally { setBusy(false) }
  }

  return (
    <>
      <div className="locpick-search">
        <input className="input" data-testid="place-search" placeholder={t('ui.search_address')} value={q} onChange={(e) => setQ(e.target.value)} aria-autocomplete="list" aria-expanded={sugs.length > 0} />
        {busy && <span className="spinner locpick-spin" />}
        {sugs.length > 0 && (
          <div className="card locpick-sugs" role="listbox">
            {sugs.map((s) => <button type="button" role="option" aria-selected={false} key={s.id} onClick={() => choose(s)}><b>{s.main}</b><span className="small muted">{s.secondary}</span></button>)}
          </div>
        )}
      </div>
      <Map className="locpick-map" defaultCenter={pos} defaultZoom={value ? 16 : 12} gestureHandling="greedy" streetViewControl={false} mapTypeControl={false} clickableIcons={false}
        onClick={(e) => { const l = e.detail.latLng; if (l) reverse(l.lat, l.lng) }}>
        <Marker position={pos} draggable onDragEnd={(e) => { const l = e.latLng; if (l) reverse(l.lat(), l.lng()) }} title={t('ui.drag_pin')} />
      </Map>
      <div className="small muted">{t('ui.click_or_drag_pin')}</div>
    </>
  )
}

export default function GooglePickMap({ apiKey, value, onChange, onError }: { apiKey: string; value: PickedLocation | null; onChange: (v: PickedLocation) => void; onError: () => void }) {
  const { lang, country } = useI18n()
  useEffect(() => { (window as any).gm_authFailure = onError; return () => { delete (window as any).gm_authFailure } }, [onError])
  return <APIProvider apiKey={apiKey} onError={onError} language={lang} region={country}><Inner value={value} onChange={onChange} /></APIProvider>
}
