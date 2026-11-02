/// <reference types="google.maps" />
import { useEffect } from 'react'
import { APIProvider, Map, useMap } from '@vis.gl/react-google-maps'
import { MarkerClusterer } from '@googlemaps/markerclusterer'
import { useNavigate } from 'react-router-dom'
import { CATS, useI18n } from '../../lib/i18n'
import { type MapPoint, ttl } from '../../lib/types'

const SEV = { low: '#31A24C', moderate: '#F7B928', high: '#F5793B', critical: '#E41E3F' }

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

/** Marker icon: severity colored circle with the hazard emoji, as an inline SVG. */
function icon(p: MapPoint): google.maps.Icon {
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40"><circle cx="20" cy="20" r="17" fill="${SEV[p.severity]}" fill-opacity="${p.is_stale ? 0.5 : 1}" stroke="#fff" stroke-width="3"/><text x="20" y="26" font-size="17" text-anchor="middle">${CATS[p.category]?.icon ?? '⚠️'}</text></svg>`
  return { url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg), scaledSize: new google.maps.Size(40, 40), anchor: new google.maps.Point(20, 20) }
}

function Markers({ points }: { points: MapPoint[] }) {
  const map = useMap()
  const nav = useNavigate()
  useEffect(() => {
    if (!map) return
    const markers = points.map((p) => {
      // Points are already approximate (rounded server side); precise reporter location never reaches the client.
      const m = new google.maps.Marker( // classic Marker keeps JSON styling working (AdvancedMarker needs a cloud Map ID)
        { position: { lat: p.lat, lng: p.lng }, icon: icon(p), title: ttl(p) })
      m.addListener('click', () => nav(`/incidents/${p.id}`))
      return m
    })
    if (points.length) { const b = new google.maps.LatLngBounds(); points.forEach((p) => b.extend({ lat: p.lat, lng: p.lng })); map.fitBounds(b, 48) }
    const c = new MarkerClusterer({ map, markers })
    return () => { c.clearMarkers(); markers.forEach((m) => m.setMap(null)) }
  }, [map, points])
  return null
}

export default function GoogleIncidentMap({ apiKey, points, dark, onError }: { apiKey: string; points: MapPoint[]; dark: boolean; onError: () => void }) {
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
        <Markers points={points} />
      </Map>
    </APIProvider>
  )
}
