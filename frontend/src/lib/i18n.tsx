import { createContext, useContext, useEffect, useState, type ReactNode } from 'react'
import i18n from 'i18next'
import { initReactI18next, useTranslation } from 'react-i18next'
import LanguageDetector from 'i18next-browser-languagedetector'
import bn from '../locales/bn.json'
import en from '../locales/en.json'

/**
 * Language registry. To add a language: create src/locales/<code>.json with the same keys as en.json,
 * import it in RESOURCES and add an entry here. RTL languages (ar, ur, fa, he) set dir automatically.
 * See docs/INTERNATIONALIZATION.md.
 */
export const LANGUAGES: { code: string; native: string; font?: string }[] = [
  { code: 'bn', native: 'বাংলা', font: 'bn' },
  { code: 'en', native: 'English' },
]
const RESOURCES = { bn: { translation: bn }, en: { translation: en } }
const RTL = ['ar', 'ur', 'fa', 'he']

i18n.use(LanguageDetector).use(initReactI18next).init({
  resources: RESOURCES,
  supportedLngs: LANGUAGES.map((l) => l.code),
  nonExplicitSupportedLngs: true,
  fallbackLng: 'en',
  interpolation: { escapeValue: false },
  // Order: saved choice, then the browser language. Bangladesh is the launch market but a browser in another language starts in English.
  detection: { order: ['localStorage', 'navigator'], lookupLocalStorage: 'rl_lang', caches: ['localStorage'] },
})

export function applyDocumentLang(l: string) {
  document.documentElement.lang = l
  document.documentElement.dir = RTL.includes(l.slice(0, 2)) ? 'rtl' : 'ltr'
}
i18n.on('languageChanged', applyDocumentLang)
applyDocumentLang(i18n.resolvedLanguage || 'en')

const ICONS: Record<string, string> = { road_accident: '🚗', flood: '🌊', fire: '🔥', electrical: '⚡', building_collapse: '🏚️', injury: '🩹', crowd_stampede: '👥', landslide: '⛰️', earthquake: '🌐', wildfire: '🌲', tsunami: '🌊', cyclone_storm: '🌀', snow_ice: '❄️', other: '⚠️' }
type Label = { icon: string } & Record<string, string>
// Category and severity labels resolve through i18next for any language: CATS[k][lang] or CATS[k].label.
const labels = (ns: string, keys: string[], icons?: Record<string, string>) =>
  Object.fromEntries(keys.map((k) => [k, new Proxy({ icon: icons?.[k] ?? '' } as Label, { get: (o, p) => (p === 'icon' ? o.icon : typeof p === 'string' ? i18n.t(`${ns}.${k}`) : undefined) })]))
export const CATS: Record<string, Label> = labels('cat', Object.keys(ICONS), ICONS)
export const SEVS: Record<string, Label> = labels('sev', ['low', 'moderate', 'high', 'critical'])
export type Key = keyof typeof en

/** Countries using miles on road signs. */
const MILES = ['US', 'GB', 'LR', 'MM']
export const COUNTRIES = ['BD', 'IN', 'PK', 'NP', 'LK', 'MM', 'ID', 'PH', 'MY', 'TR', 'SA', 'AE', 'EG', 'NG', 'KE', 'ZA', 'GB', 'US', 'CA', 'MX', 'BR', 'ES', 'FR', 'DE', 'IT', 'JP', 'AU']
function detectCountry() {
  const saved = localStorage.getItem('rl_country')
  if (saved) return saved
  const region = navigator.language.split('-')[1]?.toUpperCase()
  return region && /^[A-Z]{2}$/.test(region) ? region : 'BD'
}

type Ctx = {
  lang: string; setLang: (l: string) => void; t: (k: string, o?: Record<string, unknown>) => string
  country: string; setCountry: (c: string) => void; units: 'km' | 'mi'; setUnits: (u: 'km' | 'mi') => void
  locale: string; timeZone: string
}
const I18n = createContext<Ctx>(null!)
export function I18nProvider({ children }: { children: ReactNode }) {
  const { t, i18n: inst } = useTranslation()
  const lang = (inst.resolvedLanguage || 'en').slice(0, 2)
  const [country, setC] = useState(detectCountry)
  const [units, setU] = useState<'km' | 'mi'>(() => (localStorage.getItem('rl_units') as 'km' | 'mi') || (MILES.includes(detectCountry()) ? 'mi' : 'km'))
  useEffect(() => { localStorage.setItem('rl_country', country) }, [country])
  const value: Ctx = {
    lang, t: (k, o) => t(k, o) as string,
    setLang: (l) => { inst.changeLanguage(l) },
    country, setCountry: setC,
    units, setUnits: (u) => { localStorage.setItem('rl_units', u); setU(u) },
    locale: `${lang}-${country}`,
    timeZone: Intl.DateTimeFormat().resolvedOptions().timeZone,
  }
  return <I18n.Provider value={value}>{children}</I18n.Provider>
}
export const useI18n = () => useContext(I18n)

/** Locale aware helpers. Bangla uses Bangla digits through Intl (bn-BD). Times use the browser time zone. */
export const intlLocale = (lang: string) => (lang === 'bn' ? 'bn-BD' : lang)
export function fmtNumber(n: number, lang: string) { return new Intl.NumberFormat(intlLocale(lang)).format(n) }
export function fmtDateTime(iso: string, lang: string) { return new Intl.DateTimeFormat(intlLocale(lang), { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso)) }
export function fmtDistance(meters: number, units: 'km' | 'mi', lang: string) {
  const v = units === 'mi' ? meters / 1609.344 : meters / 1000
  return new Intl.NumberFormat(intlLocale(lang), { style: 'unit', unit: units === 'mi' ? 'mile' : 'kilometer', maximumFractionDigits: 1 }).format(v)
}
export function countryName(code: string, lang: string) {
  try { return new Intl.DisplayNames([intlLocale(lang)], { type: 'region' }).of(code) ?? code } catch { return code }
}
export function timeAgo(iso: string, lang: string) {
  const s = Math.round((new Date(iso).getTime() - Date.now()) / 1000)
  const a = Math.abs(s)
  const [v, u]: [number, Intl.RelativeTimeFormatUnit] = a < 60 ? [s, 'second'] : a < 3600 ? [Math.round(s / 60), 'minute'] : a < 86400 ? [Math.round(s / 3600), 'hour'] : [Math.round(s / 86400), 'day']
  return new Intl.RelativeTimeFormat(intlLocale(lang), { numeric: 'auto', style: 'short' }).format(v, u)
}
export default i18n
