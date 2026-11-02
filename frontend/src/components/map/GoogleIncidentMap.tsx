import type { MapFocus } from '../../lib/countryCenter'
/// <reference types="google.maps" />
import { useEffect } from 'react'
import { APIProvider, Map, useMap } from '@vis.gl/react-google-maps'
import { MarkerClusterer } from '@googlemaps/markerclusterer'
import { useNavigate } from 'react-router-dom'
import { useI18n } from '../../lib/i18n'
import { type MapPoint, ttl } from '../../lib/types'
import { pinSvg, heatSvg } from '../../lib/mapPin'


// Facebook themed map style: muted land, blue water, less POI noise so incidents stand out.
const FB_STYLE: google.maps.MapTypeStyle[] = [
  { elementType: 'geometry', stylers: [{ color: '#F0F2F5' }] },
  { elementType: 'labels.text.fill', stylers: [{ color: '#65676B' }] },
  { elementType: 'labels.text.stroke', stylers: [{ color: '#FFFFFF' }] },
  { featureType: 'poi', stylers: [{ visibility: 'off' }] },
  { featureType: 'transit', stylers: [{ visibility: 'off' }] },
  { featureType: 'road', elementType: 'geometry', stylers: [{ color: '#FFFFFF' }] },
  { featureType: 'road.highway', elementType: 'geometry', stylers: [{ color: '#CED0D4' }] },
  { featureType: 'water', elementType: 'geometry', stylers: [{ color: '#B9D3F7' }] },
]

const FB_DARK: google.maps.MapTypeStyle[] = [
  { elementType: "geometry", stylers: [{ color: "#242526" }] },
  { elementType: "labels.text.fill", stylers: [{ color: "#B0B3B8" }] },
  { elementType: "labels.text.stroke", stylers: [{ color: "#18191A" }] },
  { featureType: "poi", stylers: [{ visibility: "off" }] },
  { featureType: "transit", stylers: [{ visibility: "off" }] },
  { featureType: "road", elementType: "geometry", stylers: [{ color: "#3A3B3C" }] },
  { featureType: "water", elementType: "geometry", stylers: [{ color: "#18191A" }] },
]

/** Marker icon: Meta blue pin with the Lucide incident type icon (same design as the app). */
function icon(p: MapPoint, dark: boolean): google.maps.Icon {
  return { url: pinSvg(p.category, dark, p.is_stale), scaledSize: new google.maps.Size(40, 48), anchor: new google.maps.Point(20, 46) }
}

/** Hot area layer: screen sized glows weighted by severity, drawn under the pins and clusters. An empty area never means a road is safe. */
function Heat({ points, dark }: { points: MapPoint[]; dark: boolean }) {
  const map = useMap()
  useEffect(() => {
    if (!map) return
    const circles = points.map((p) => { const h = heatSvg(dark, p.severity); return new google.maps.Marker({ map, position: { lat: p.lat, lng: p.lng }, clickable: false, zIndex: 0,
      icon: { url: h.url, scaledSize: new google.maps.Size(h.size, h.size), anchor: new google.maps.Point(h.size / 2, h.size / 2) } }) })
    return () => circles.forEach((c) => c.setMap(null))
  }, [map, points, dark])
  return null
}

function Markers({ points, focus, dark }: { points: MapPoint[]; focus: MapFocus; dark: boolean }) {
  const map = useMap()
  const nav = useNavigate()
  useEffect(() => {
    if (!map) return
    const markers = points.map((p) => {
      // Points are already approximate (rounded server side); precise reporter location never reaches the client.
      const m = new google.maps.Marker( // classic Marker keeps JSON styling working (AdvancedMarker needs a cloud Map ID)
        { position: { lat: p.lat, lng: p.lng }, icon: icon(p, dark), title: ttl(p) })
      m.addListener('click', () => nav(`/incidents/${p.id}`))
      return m
    })
    const c = new MarkerClusterer({ map, markers })
    return () => { c.clearMarkers(); markers.forEach((m) => m.setMap(null)) }
  }, [map, points, dark])
  // Camera: a focus (nearby or the active country) wins; otherwise fit every point (world view).
  useEffect(() => {
    if (!map) return
    if (focus) { map.setCenter({ lat: focus.lat, lng: focus.lng }); map.setZoom(focus.zoom) }
    else if (points.length) { const b = new google.maps.LatLngBounds(); points.forEach((p) => b.extend({ lat: p.lat, lng: p.lng })); map.fitBounds(b, 48) }
  }, [map, focus, points.length]) // eslint-disable-line react-hooks/exhaustive-deps
  return null
}

export default function GoogleIncidentMap({ apiKey, points, dark, onError, focus = null, heat = false }: { apiKey: string; points: MapPoint[]; dark: boolean; onError: () => void; focus?: MapFocus; heat?: boolean }) {
  useEffect(() => {
    // Google calls this global when the key is rejected (billing, referrer or API restrictions).
    ;(window as any).gm_authFailure = onError
    return () => { delete (window as any).gm_authFailure }
  }, [onError])
  const { lang, country } = useI18n()
  return (
    <APIProvider apiKey={apiKey} onError={onError} language={lang} region={country}>
      <Map style={{ height: '100%', minHeight: 420, borderRadius: 8 }} defaultCenter={{ lat: 23.78, lng: 90.39 }} defaultZoom={12}
        styles={dark ? FB_DARK : FB_STYLE} gestureHandling="greedy" streetViewControl={false} mapTypeControl={false}>
        {heat && <Heat points={points} dark={dark} />}
        <Markers points={points} focus={focus} dark={dark} />
      </Map>
    </APIProvider>
  )
}
