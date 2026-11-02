import { useAuth } from '../lib/auth'
import { api, errMsg } from '../lib/api'
import { COUNTRIES, countryName, useI18n } from '../lib/i18n'
import { LanguagePicker } from '../components/LanguagePicker'
import { useTheme } from '../components/Shell'
import { useToast } from '../components/Toast'
import { Sk } from '../components/Skeleton'
import { useConfirm } from '../components/Confirm'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import type { Incident } from '../lib/types'
import { Bot, TriangleAlert, Trash2, RotateCcw } from 'lucide-react'

const ZONES: string[] = (Intl as any).supportedValuesOf ? (Intl as any).supportedValuesOf('timeZone') : ['Asia/Dhaka', 'UTC']

/** Facebook style settings: grouped cards with a row per setting. */
export default function Settings() {
  const { user, refresh } = useAuth()
  const { t, lang, country, setCountry, units, setUnits, timeZone } = useI18n()
  const confirm = useConfirm()
  const theme = useTheme()
  const toast = useToast()
  const save = async (body: Record<string, unknown>) => { if (!user) return; try { await api('/auth/me', { method: 'PATCH', body }); await refresh(); toast(t('ui.saved')) } catch (e) { toast(errMsg(e)) } }
  const row = (label: string, control: React.ReactNode, hint?: string) => (
    <div className="setting-row"><div className="grow"><div>{label}</div>{hint && <div className="small muted">{hint}</div>}</div>{control}</div>)
  if (!user) return <div className="main"><div className="card" style={{ padding: 16 }}><Sk h={24} w="40%" /><Sk h={16} style={{ marginTop: 12 }} /></div></div>
  return (
    <div className="main">
      <h1 style={{ margin: '4px 4px 0' }}>{t('ui.settings')}</h1>
      <div className="card settings-card" id="account">
        <div className="settings-head">{t('ui.account')}</div>
        <AccountForm />
      </div>
      <AlertArea />
      <div className="card settings-card">
        <div className="settings-head">{t('ui.appearance')}</div>
        {row(t('ui.dark_mode'), <label className="switch"><input type="checkbox" checked={theme.theme === 'dark'} onChange={theme.toggle} /><span /></label>)}
      </div>
      <div className="card settings-card">
        <div className="settings-head">{t('ui.region_language')}</div>
        {row(t('ui.language'), <LanguagePicker variant="field" />)}
        {row(t('ui.country'), <select className="input sel" value={country} onChange={(e) => { setCountry(e.target.value); save({ country_code: e.target.value }) }}>{COUNTRIES.map((c) => <option key={c} value={c}>{countryName(c, lang)}</option>)}</select>, t('ui.country_hint'))}
        {row(t('ui.units'), <select className="input sel" value={units} onChange={(e) => { const u = e.target.value as 'km' | 'mi'; setUnits(u); save({ distance_unit: u }) }}><option value="km">{t('ui.km')}</option><option value="mi">{t('ui.mi')}</option></select>)}
        {row(t('ui.timezone'), <select className="input sel" defaultValue={timeZone} onChange={(e) => save({ timezone: e.target.value })}>{ZONES.map((z) => <option key={z} value={z}>{z}</option>)}</select>)}
      </div>
      <div className="card settings-card">
        <div className="settings-head">{t('ui.privacy_ai')}</div>
        {row(t('ui.ai_analysis_consent'), <label className="switch"><input type="checkbox" checked={user.ai_consent} onChange={(e) => save({ ai_consent: e.target.checked })} /><span /></label>, t('ui.ai_consent_hint'))}
        {row(t('ui.auto_post'), <label className="switch"><input type="checkbox" data-testid="auto-post" checked={!!user.auto_post_enabled} onChange={async (e) => {
          const on = e.target.checked
          if (on && !(await confirm({ title: t('ui.auto_post'), body: t('ui.auto_post_consent'), confirm: t('ui.enable'), danger: false, icon: <Bot size={26} /> }))) return
          save({ auto_post_enabled: on, ...(on ? { auto_post_consent: true } : {}) })
        }} /><span /></label>, t('ui.auto_post_hint'))}
      </div>
      <TrashCard />
      <div className="card settings-card"><Link className="setting-row" to="/profile">← {t('ui.back_to_profile')}</Link></div>
    </div>
  )
}

export function AlertArea() {
  const { user, refresh } = useAuth()
  const { t } = useI18n()
  const toast = useToast()
  const [radius, setRadius] = useState(user?.alert_radius_km ?? 5)
  if (!user) return null
  const save = async (body: any) => { try { await api('/auth/me', { method: 'PATCH', body }); await refresh(); toast(t('ui.saved')) } catch (e) { toast(errMsg(e)) } }
  const setArea = () => navigator.geolocation?.getCurrentPosition((p) => save({ alert_lat: +p.coords.latitude.toFixed(4), alert_lng: +p.coords.longitude.toFixed(4), alert_radius_km: radius }), () => toast(t('ui.location_unavailable')))
  return (
    <>
      <div className="card" style={{ padding: 16, display: 'grid', gap: 12 }}>
        <b>{t('ui.nearby_alerts')}</b>
        <div className="small muted">{user.alert_area_set ? (t('ui.alert_area_is_set')) : (t('ui.no_alert_area_set'))}</div>
        <label className="small">{t('ui.radius')}: {radius} km<input type="range" min={1} max={50} value={radius} onChange={(e) => setRadius(+e.target.value)} style={{ width: '100%' }} /></label>
        <button className="btn btn-primary" onClick={setArea}>{t('ui.use_my_current_area')}</button>
        <label className="row small"><input type="checkbox" checked={user.ai_consent} onChange={(e) => save({ ai_consent: e.target.checked })} /> {t('ui.ai_analysis_consent')}</label>
      </div>
    </>
  )
}

/** Trash: Restore or Delete permanently per item, and Empty Trash. Permanent actions use a red confirmation. */
export function TrashCard({ all = false }: { all?: boolean }) {
  const { t } = useI18n()
  const toast = useToast()
  const confirm = useConfirm()
  const qc = useQueryClient()
  const q = useQuery({ queryKey: ['trash', all], queryFn: () => api<{ data: Incident[] }>('/me/trash' + (all ? '?all=1' : '')) })
  const items = q.data?.data ?? []
  const done = () => { qc.invalidateQueries({ queryKey: ['trash'] }); qc.invalidateQueries({ queryKey: ['feed'] }) }
  const restore = async (i: Incident) => { try { await api(`/incidents/${i.id}/restore`, { method: 'POST' }); toast(t('ui.restored')); done() } catch (e) { toast(errMsg(e), undefined, undefined, { kind: 'error' }) } }
  const purge = async (i: Incident) => {
    if (!(await confirm({ title: t('ui.delete_forever_q'), body: t('ui.delete_forever_body'), confirm: t('ui.delete_forever'), icon: <TriangleAlert size={26} /> }))) return
    try { await api(`/incidents/${i.id}/force`, { method: 'DELETE' }); toast(t('ui.deleted_forever')); done() } catch (e) { toast(errMsg(e), undefined, undefined, { kind: 'error' }) }
  }
  const empty = async () => {
    if (!(await confirm({ title: t('ui.empty_trash_q'), body: t('ui.empty_trash_body', { count: items.length }), confirm: t('ui.empty_trash'), icon: <TriangleAlert size={26} /> }))) return
    try { await api('/me/trash' + (all ? '?all=1' : ''), { method: 'DELETE' }); toast(t('ui.trash_emptied')); done() } catch (e) { toast(errMsg(e), undefined, undefined, { kind: 'error' }) }
  }
  return (
    <div className="card settings-card" id="trash" data-testid="trash-card">
      <div className="settings-head row"><span className="grow"><Trash2 size={18} style={{ verticalAlign: -3 }} /> {t('ui.trash')}</span>{items.length > 0 && <button className="btn btn-danger" data-testid="empty-trash" onClick={empty}>{t('ui.empty_trash')}</button>}</div>
      {q.isLoading ? <div style={{ padding: 12 }}><Sk h={16} /><Sk h={16} style={{ marginTop: 8 }} /></div>
        : items.length === 0 ? <div className="muted small" style={{ padding: '8px 16px 16px' }}>{t('ui.trash_empty')}</div>
        : items.map((i) => <div key={i.id} className="setting-row" data-testid="trash-item">
            <div className="grow"><div>{i.title}</div><div className="small muted">{t('ui.trash_deleted_at', { date: new Date((i as any).deleted_at ?? i.created_at).toLocaleString() })}</div></div>
            <button className="btn" onClick={() => restore(i)}><RotateCcw size={16} /> {t('ui.restore')}</button>
            <button className="btn btn-danger" data-testid="delete-forever" onClick={() => purge(i)}>{t('ui.delete_forever')}</button>
          </div>)}
    </div>
  )
}

function AccountForm() {
  const { user, refresh } = useAuth()
  const { t } = useI18n()
  const toast = useToast()
  const [name, setName] = useState(user?.name ?? '')
  const save = async () => { try { await api('/auth/me', { method: 'PATCH', body: { name } }); await refresh(); toast(t('ui.saved')) } catch (e) { toast(errMsg(e), undefined, undefined, { kind: 'error' }) } }
  return <div className="setting-row"><div className="grow"><div>{t('ui.name')}</div><input className="input" value={name} onChange={(e) => setName(e.target.value)} maxLength={80} /></div><button className="btn btn-primary" disabled={!name.trim() || name === user?.name} onClick={save}>{t('ui.save')}</button></div>
}
