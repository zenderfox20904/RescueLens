import { cloneElement, isValidElement, type ReactElement } from 'react'
import { renderToStaticMarkup } from 'react-dom/server'
import { CAT_ICON } from './icons'

/** Meta blue map pin with the Lucide incident type icon, matching the app. Follows the light and dark theme. */
const cache = new Map<string, string>()
export function pinSvg(category: string, dark: boolean, stale = false): string {
  const key = `${category}:${dark}:${stale}`
  const hit = cache.get(key)
  if (hit) return hit
  const el = CAT_ICON[category] ?? CAT_ICON.other
  const glyph = isValidElement(el) ? renderToStaticMarkup(cloneElement(el as ReactElement<any>, { size: 18, color: '#FFFFFF', strokeWidth: 2.4, x: 11, y: 9 })) : ''
  const blue = dark ? '#2D88FF' : '#0866FF'
  const ring = dark ? '#18191A' : '#FFFFFF'
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="40" height="48" viewBox="0 0 40 48"><defs><filter id="s" x="-30%" y="-30%" width="160%" height="160%"><feDropShadow dx="0" dy="1.5" stdDeviation="1.5" flood-opacity="0.35"/></filter></defs><g filter="url(#s)" opacity="${stale ? 0.55 : 1}"><path d="M20 46 L13 33 H27 Z" fill="${ring}"/><circle cx="20" cy="18" r="16" fill="${ring}"/><circle cx="20" cy="18" r="13" fill="${blue}"/></g>${glyph}</svg>`
  const url = 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg)
  cache.set(key, url)
  return url
}

/** Heat colors for the hot area layer, tuned per theme so the glow reads on both basemaps. */
export const HEAT = { dark: ['#FFB020', '#FF6A3D', '#FF2D55'], light: ['#F7B928', '#F5793B', '#E41E3F'] }
export const SEV_W: Record<string, number> = { low: 1, moderate: 2, high: 3, critical: 4 }

/** Hot area glow as a screen sized radial gradient, so it reads the same at every zoom. Size grows with severity. */
export function heatSvg(dark: boolean, severity: string): { url: string; size: number } {
  const w = SEV_W[severity] ?? 1
  const size = Math.round(90 + w * 22)
  const [a, b, c] = dark ? HEAT.dark : HEAT.light
  const o = dark ? 1 : 0.85
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}"><defs><radialGradient id="g"><stop offset="0" stop-color="${c}" stop-opacity="${0.55 * o}"/><stop offset="0.4" stop-color="${b}" stop-opacity="${0.32 * o}"/><stop offset="0.75" stop-color="${a}" stop-opacity="${0.14 * o}"/><stop offset="1" stop-color="${a}" stop-opacity="0"/></radialGradient></defs><circle cx="${size / 2}" cy="${size / 2}" r="${size / 2}" fill="url(#g)"/></svg>`
  return { url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg), size }
}
