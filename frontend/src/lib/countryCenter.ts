/** Approximate center and a zoom that fits the country, used to open the map on the user's active country. */
export const COUNTRY_CENTER: Record<string, [number, number, number]> = {
  BD: [23.7, 90.35, 7], IN: [22.5, 79, 5], PK: [30, 70, 5], NP: [28.3, 84.1, 7], LK: [7.8, 80.7, 7], MM: [19.5, 96, 5], ID: [-2.5, 118, 4],
  PH: [12.5, 122, 5], MY: [4, 109, 5], TR: [39, 35, 5], SA: [24, 45, 5], AE: [24.3, 54.3, 7], EG: [26.8, 30.8, 5], NG: [9.1, 8.7, 6],
  KE: [0.2, 37.9, 6], ZA: [-29, 25, 5], GB: [54, -2.5, 5], US: [39.5, -98.5, 4], CA: [56, -96, 3], MX: [23.6, -102.5, 5], BR: [-14, -52, 4],
  ES: [40.2, -3.7, 6], FR: [46.6, 2.4, 6], DE: [51.1, 10.4, 6], IT: [42.8, 12.5, 6], JP: [36.2, 138.2, 5], AU: [-25.3, 134, 4],
}
export type MapFocus = { lat: number; lng: number; zoom: number } | null
