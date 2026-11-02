import { useAuth } from '../lib/auth'
import { api, errMsg } from '../lib/api'
import { COUNTRIES, LANGUAGES, countryName, useI18n } from '../lib/i18n'
import { useTheme } from '../components/Shell'
import { useToast } from '../components/Toast'
import { Sk } from '../components/Skeleton'

const ZONES: string[] = (Intl as any).supportedValuesOf ? (Intl as any).supportedValuesOf('timeZone') : ['Asia/Dhaka', 'UTC']

/** Facebook style settings: grouped cards with a row per setting. */
export default function Settings() {
  const { user, refresh } = useAuth()
  const { t, lang, setLang, country, setCountry, units, setUnits, timeZone } = useI18n()
  const theme = useTheme()
  const toast = useToast()
  const save = async (body: Record<string, unknown>) => { if (!user) return; try { await api('/auth/me', { method: 'PATCH', body }); await refresh(); toast(t('ui.saved')) } catch (e) { toast(errMsg(e)) } }
  const row = (label: string, control: React.ReactNode, hint?: string) => (
    <div className="setting-row"><div className="grow"><div>{label}</div>{hint && <div className="small muted">{hint}</div>}</div>{control}</div>)
  if (!user) return <div className="main"><div className="card" style={{ padding: 16 }}><Sk h={24} w="40%" /><Sk h={16} style={{ marginTop: 12 }} /></div></div>
  return (
    <div className="main">
      <h1 style={{ margin: '4px 4px 0' }}>{t('ui.settings')}</h1>
      <div className="card settings-card">
        <div className="settings-head">{t('ui.appearance')}</div>
        {row(t('ui.dark_mode'), <label className="switch"><input type="checkbox" checked={theme.theme === 'dark'} onChange={theme.toggle} /><span /></label>)}
      </div>
      <div className="card settings-card">
        <div className="settings-head">{t('ui.region_language')}</div>
        {row(t('ui.language'), <select className="input sel" value={lang} onChange={(e) => { setLang(e.target.value); save({ locale: e.target.value }) }}>{LANGUAGES.map((l) => <option key={l.code} value={l.code}>{l.native}</option>)}</select>)}
        {row(t('ui.country'), <select className="input sel" value={country} onChange={(e) => { setCountry(e.target.value); save({ country_code: e.target.value }) }}>{COUNTRIES.map((c) => <option key={c} value={c}>{countryName(c, lang)}</option>)}</select>, t('ui.country_hint'))}
        {row(t('ui.units'), <select className="input sel" value={units} onChange={(e) => { const u = e.target.value as 'km' | 'mi'; setUnits(u); save({ distance_unit: u }) }}><option value="km">{t('ui.km')}</option><option value="mi">{t('ui.mi')}</option></select>)}
        {row(t('ui.timezone'), <select className="input sel" defaultValue={timeZone} onChange={(e) => save({ timezone: e.target.value })}>{ZONES.map((z) => <option key={z} value={z}>{z}</option>)}</select>)}
      </div>
      <div className="card settings-card">
        <div className="settings-head">{t('ui.privacy_ai')}</div>
        {row(t('ui.ai_analysis_consent'), <label className="switch"><input type="checkbox" checked={user.ai_consent} onChange={(e) => save({ ai_consent: e.target.checked })} /><span /></label>, t('ui.ai_consent_hint'))}
        {row(t('ui.auto_post'), <label className="switch"><input type="checkbox" data-testid="auto-post" checked={!!user.auto_post_enabled} onChange={(e) => {
          if (e.target.checked && !window.confirm(t('ui.auto_post_consent'))) return
          save({ auto_post_enabled: e.target.checked, ...(e.target.checked ? { auto_post_consent: true } : {}) })
        }} /><span /></label>, t('ui.auto_post_hint'))}
      </div>
    </div>
  )
}
