/** Approximate center and a zoom that fits the country, used to open the map on the user's active country. */
export const COUNTRY_CENTER: Record<string, [number, number, number]> = {
  BD: [23.7, 90.35, 7], IN: [22.5, 79, 5], PK: [30, 70, 5], NP: [28.3, 84.1, 7], LK: [7.8, 80.7, 7], MM: [19.5, 96, 5], ID: [-2.5, 118, 4],
  PH: [12.5, 122, 5], MY: [4, 109, 5], TR: [39, 35, 5], SA: [24, 45, 5], AE: [24.3, 54.3, 7], EG: [26.8, 30.8, 5], NG: [9.1, 8.7, 6],
  KE: [0.2, 37.9, 6], ZA: [-29, 25, 5], GB: [54, -2.5, 5], US: [39.5, -98.5, 4], CA: [56, -96, 3], MX: [23.6, -102.5, 5], BR: [-14, -52, 4],
  ES: [40.2, -3.7, 6], FR: [46.6, 2.4, 6], DE: [51.1, 10.4, 6], IT: [42.8, 12.5, 6], JP: [36.2, 138.2, 5], AU: [-25.3, 134, 4],
}
export type MapFocus = { lat: number; lng: number; zoom: number } | null

/** Rough bounding boxes [minLat, maxLat, minLng, maxLng], smallest first so a border city resolves to the smaller country. */
const BBOX: [string, number, number, number, number][] = [
  ['AE', 22.6, 26.1, 51.5, 56.4], ['LK', 5.9, 9.9, 79.6, 81.9], ['BD', 20.6, 26.7, 88.0, 92.7], ['NP', 26.3, 30.5, 80.0, 88.2],
  ['GB', 49.9, 60.9, -8.2, 1.8], ['KE', -4.7, 5.0, 33.9, 41.9], ['MY', 0.8, 7.4, 99.6, 119.3], ['PH', 4.6, 21.2, 116.9, 126.6],
  ['IT', 36.6, 47.1, 6.6, 18.5], ['DE', 47.3, 55.1, 5.9, 15.0], ['FR', 41.3, 51.1, -5.1, 9.6], ['ES', 36.0, 43.8, -9.3, 3.3],
  ['JP', 24.0, 45.6, 122.9, 145.8], ['NG', 4.3, 13.9, 2.7, 14.7], ['EG', 22.0, 31.7, 24.7, 36.9], ['TR', 35.8, 42.1, 26.0, 44.8],
  ['MM', 9.8, 28.5, 92.2, 101.2], ['PK', 23.7, 37.1, 60.9, 77.8], ['SA', 16.4, 32.2, 34.5, 55.7], ['ZA', -34.8, -22.1, 16.5, 32.9],
  ['MX', 14.5, 32.7, -118.4, -86.7], ['IN', 6.7, 35.5, 68.1, 97.4], ['ID', -11.0, 6.1, 95.0, 141.0], ['AU', -43.7, -10.7, 113.3, 153.6],
  ['BR', -33.8, 5.3, -73.99, -34.8], ['US', 24.5, 49.4, -124.8, -66.9], ['CA', 41.7, 83.1, -141.0, -52.6],
]
export function countryAt(lat: number, lng: number): string | null {
  const hit = BBOX.find(([, a, b, c, d]) => lat >= a && lat <= b && lng >= c && lng <= d)
  return hit ? hit[0] : null
}
