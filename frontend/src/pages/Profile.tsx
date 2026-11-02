import { useState } from 'react'
import { useAuth } from '../lib/auth'
import { api, errMsg } from '../lib/api'
import { COUNTRIES, LANGUAGES, countryName, useI18n } from '../lib/i18n'
import { Avatar } from '../components/Shell'
import { Sk } from '../components/Skeleton'
import { useToast } from '../components/Toast'

export default function Profile() {
  const { user, loading, refresh } = useAuth()
  const { t, lang, setLang, country, setCountry, units, setUnits } = useI18n()
  const toast = useToast()
  const [radius, setRadius] = useState(user?.alert_radius_km ?? 5)
  if (loading || !user) return <div className="main"><div className="card" style={{ padding: 16 }}><div className="row"><Sk w={96} h={96} r={48} /><div className="grow"><Sk w="50%" h={20} /><Sk w="30%" h={12} style={{ marginTop: 8 }} /></div></div></div></div>
  const save = async (body: any) => { try { await api('/auth/me', { method: 'PATCH', body }); await refresh(); toast(t('ui.saved')) } catch (e) { toast(errMsg(e)) } }
  const setArea = () => navigator.geolocation?.getCurrentPosition((p) => save({ alert_lat: +p.coords.latitude.toFixed(4), alert_lng: +p.coords.longitude.toFixed(4), alert_radius_km: radius }), () => toast(t('ui.location_unavailable')))
  return (
    <div className="main">
      <div className="card" style={{ overflow: 'hidden' }}>
        <div style={{ height: 160, background: 'linear-gradient(135deg, var(--grad-from), var(--grad-to))' }} />
        <div className="row" style={{ padding: '0 16px 16px', marginTop: -48, alignItems: 'flex-end' }}>
          <div style={{ border: '4px solid var(--card)', borderRadius: '50%' }}><Avatar name={user.name} size={112} /></div>
          <div className="grow"><h1 style={{ margin: 0 }}>{user.name}</h1><div className="muted">{user.email} · {user.role}{user.is_demo ? ' · DEMO' : ''}</div></div>
        </div>
      </div>
      <div className="card" style={{ padding: 16, display: 'grid', gap: 12 }}>
        <b>{t('ui.nearby_alerts')}</b>
        <div className="small muted">{user.alert_area_set ? (t('ui.alert_area_is_set')) : (t('ui.no_alert_area_set'))}</div>
        <label className="small">{t('ui.radius')}: {radius} km<input type="range" min={1} max={50} value={radius} onChange={(e) => setRadius(+e.target.value)} style={{ width: '100%' }} /></label>
        <button className="btn btn-primary" onClick={setArea}>{t('ui.use_my_current_area')}</button>
        <label className="row small"><input type="checkbox" checked={user.ai_consent} onChange={(e) => save({ ai_consent: e.target.checked })} /> {t('ui.ai_analysis_consent')}</label>
        <label className="row small">{t('ui.language')}<select className="input" style={{ width: 160 }} value={lang} onChange={(e) => { setLang(e.target.value); save({ locale: e.target.value }) }}>{LANGUAGES.map((l) => <option key={l.code} value={l.code}>{l.native}</option>)}</select></label>
        <label className="row small">{t('ui.country')}<select className="input" style={{ width: 220 }} value={country} onChange={(e) => { setCountry(e.target.value); save({ country_code: e.target.value }) }}>{COUNTRIES.map((c) => <option key={c} value={c}>{countryName(c, lang)}</option>)}</select></label>
        <label className="row small">{t('ui.units')}<select className="input" style={{ width: 160 }} value={units} onChange={(e) => { const u = e.target.value as 'km' | 'mi'; setUnits(u); save({ distance_unit: u }) }}><option value="km">{t('ui.km')}</option><option value="mi">{t('ui.mi')}</option></select></label>
      </div>
    </div>
  )
}
