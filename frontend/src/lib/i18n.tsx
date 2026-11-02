import { createContext, useContext, useEffect, useState, type ReactNode } from 'react'
import i18n from 'i18next'
import { initReactI18next, useTranslation } from 'react-i18next'
import HttpBackend from 'i18next-http-backend'
import bn from '../locales/bn.json'
import en from '../locales/en.json'

/**
 * Worldwide languages. The list comes from GET /languages (admin managed). en and bn are hand translated and bundled;
 * every other language is loaded through the i18next http backend from GET /i18n/{code}, which machine translates
 * missing strings once through the AI provider and caches them. Untranslated keys fall back to English.
 * See docs/INTERNATIONALIZATION.md.
 */
export type Language = { code: string; native_name: string; english_name: string; rtl: boolean; hand_translated?: boolean }
export const BUNDLED: Language[] = [
  { code: 'en', native_name: 'English', english_name: 'English', rtl: false, hand_translated: true },
  { code: 'bn', native_name: 'বাংলা', english_name: 'Bengali', rtl: false, hand_translated: true },
]
/** Kept for older imports: the two bundled languages. */
export const LANGUAGES = BUNDLED.map((l) => ({ code: l.code, native: l.native_name }))
const RTL = ['ar', 'ur', 'fa', 'he', 'ps', 'sd', 'yi', 'ckb']
const API_BASE = (import.meta.env.VITE_API_URL as string) || 'http://127.0.0.1:8000/api/v1'

i18n.use(HttpBackend).use(initReactI18next).init({
  resources: { en: { translation: en }, bn: { translation: bn } },
  partialBundledLanguages: true,
  backend: { loadPath: `${API_BASE}/i18n/{{lng}}`, requestOptions: { cache: 'default' } },
  fallbackLng: 'en',
  load: 'languageOnly',
  interpolation: { escapeValue: false },
  // English by default. Only an explicit choice in the switcher (saved in rl_lang, and on the profile) changes it.
  // No browser or region language detection.
  lng: localStorage.getItem('rl_lang') || 'en',
})

/** Switch language: remembered on this device and, for signed in users, on the profile. */
export async function chooseLanguage(code: string) {
  localStorage.setItem('rl_lang', code)
  await i18n.changeLanguage(code)
}

export function applyDocumentLang(l: string) {
  document.documentElement.lang = l
  document.documentElement.dir = RTL.includes(l.split('-')[0]) ? 'rtl' : 'ltr'
}
i18n.on('languageChanged', applyDocumentLang)
applyDocumentLang(i18n.language || 'en')

import { CAT_ICON as ICONS } from './icons'
type Label = { icon: React.ReactNode } & Record<string, string>
// Category and severity labels resolve through i18next for any language: CATS[k][lang] or CATS[k].label.
const labels = (ns: string, keys: string[], icons?: Record<string, React.ReactNode>) =>
  Object.fromEntries(keys.map((k) => [k, new Proxy({ icon: icons?.[k] ?? null } as unknown as Label, { get: (o, p) => (p === 'icon' ? o.icon : typeof p === 'string' ? i18n.t(`${ns}.${k}`) : undefined) })]))
export const CATS: Record<string, Label> = labels('cat', Object.keys(ICONS), ICONS)
export const SEVS: Record<string, Label> = labels('sev', ['low', 'moderate', 'high', 'critical'])
export type Key = keyof typeof en

/** Countries using miles on road signs. */
const MILES = ['US', 'GB', 'LR', 'MM']
export const COUNTRIES = ['BD', 'IN', 'PK', 'NP', 'LK', 'MM', 'ID', 'PH', 'MY', 'TR', 'SA', 'AE', 'EG', 'NG', 'KE', 'ZA', 'GB', 'US', 'CA', 'MX', 'BR', 'ES', 'FR', 'DE', 'IT', 'JP', 'AU']
/** Starting value only. The real active country comes from device location, then profile, then IP (see I18nProvider). Never the browser language region. */
function detectCountry() {
  return localStorage.getItem('rl_country') || 'BD'
}

type Ctx = {
  lang: string; setLang: (l: string) => void; t: (k: string, o?: Record<string, unknown>) => string
  country: string; setCountry: (c: string) => void; units: 'km' | 'mi'; setUnits: (u: 'km' | 'mi') => void
  locale: string; timeZone: string
}
const I18n = createContext<Ctx>(null!)
export function I18nProvider({ children }: { children: ReactNode }) {
  const { t, i18n: inst } = useTranslation()
  const lang = inst.language || 'en'
  const [country, setC] = useState(detectCountry)
  const [units, setU] = useState<'km' | 'mi'>(() => (localStorage.getItem('rl_units') as 'km' | 'mi') || (MILES.includes(detectCountry()) ? 'mi' : 'km'))
  useEffect(() => { localStorage.setItem('rl_country', country) }, [country])
  // Active country follows the device location, then the profile country, then the IP country, unless the user picked one in Settings.
  useEffect(() => {
    if (localStorage.getItem('rl_country_manual')) return
    let done = false
    const fallback = async () => {
      if (done) return
      const { api } = await import('./api')
      try { const me = await api<{ user?: { country_code?: string } }>('/auth/me'); const c = me.user?.country_code; if (c && !done) { done = true; setC(c.toUpperCase()); return } } catch { /* signed out */ }
      try { const ip = await api<{ country_code: string | null }>('/geo/ip'); if (ip.country_code && !done) { done = true; setC(ip.country_code) } } catch { /* offline */ }
    }
    if (!navigator.geolocation) { void fallback(); return }
    navigator.geolocation.getCurrentPosition(async (p) => {
      const { countryAt } = await import('./countryCenter')
      const c = countryAt(p.coords.latitude, p.coords.longitude)
      if (c) { done = true; setC(c) } else void fallback()
    }, () => void fallback(), { maximumAge: 600000, timeout: 8000 })
  }, [])
  const value: Ctx = {
    lang, t: (k, o) => t(k, o) as string,
    setLang: (l) => { chooseLanguage(l) },
    country, setCountry: (c) => { localStorage.setItem('rl_country_manual', '1'); setC(c) },
    units, setUnits: (u) => { localStorage.setItem('rl_units', u); setU(u) },
    locale: `${lang}-${country}`,
    timeZone: Intl.DateTimeFormat().resolvedOptions().timeZone,
  }
  return <I18n.Provider value={value}>{children}</I18n.Provider>
}
export const useI18n = () => useContext(I18n)

/** Locale aware helpers. Bangla uses Bangla digits through Intl (bn-BD). Times use the browser time zone. */
export const intlLocale = (lang: string) => (lang === 'bn' ? 'bn-BD' : lang === 'ar' ? 'ar-EG' : lang)
export const isRtl = (lang: string) => RTL.includes(lang.split('-')[0])
export function fmtNumber(n: number, lang: string, digits?: number) { return new Intl.NumberFormat(intlLocale(lang), digits == null ? undefined : { maximumFractionDigits: digits, minimumFractionDigits: digits }).format(n) }
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
