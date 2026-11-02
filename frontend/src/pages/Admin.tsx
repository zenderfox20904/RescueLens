import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { api, errMsg } from '../lib/api'
import { CATS, countryName, fmtDateTime, fmtNumber, useI18n } from '../lib/i18n'
import { useAuth } from '../lib/auth'
import { ListSkeleton, Sk } from '../components/Skeleton'
import { useToast } from '../components/Toast'
import { IChart, IGlobe, IPhone, IShield, IUser, IFlag } from '../components/Icons'
import Moderation from './Moderation'

type Section = 'overview' | 'moderation' | 'users' | 'directory' | 'audit'

/** System admin panel in a Meta Business Suite style: left rail, page header, KPI tiles and tables. */
export default function Admin() {
  const { t } = useI18n()
  const { user } = useAuth()
  const [sec, setSec] = useState<Section>('overview')
  const isAdmin = user?.role === 'admin'
  const items: [Section, React.ReactNode, string, boolean][] = [
    ['overview', <IChart size={20} />, t('ui.overview'), true],
    ['moderation', <IShield size={20} />, t('mod'), true],
    ['users', <IUser size={20} />, t('ui.users'), true],
    ['directory', <IPhone size={20} />, t('ui.emergency_directory'), isAdmin],
    ['audit', <IFlag size={20} />, t('ui.audit_log'), isAdmin],
  ]
  return (
    <div className="biz">
      <aside className="biz-rail">
        <div className="biz-brand"><IGlobe size={22} /><div><b>RescueLens</b><div className="small muted">{t('ui.admin_panel')}</div></div></div>
        {items.filter((i) => i[3]).map(([k, icon, label]) => (
          <button key={k} className={'biz-item' + (sec === k ? ' on' : '')} onClick={() => setSec(k)}>{icon}<span>{label}</span></button>))}
      </aside>
      <section className="biz-main">
        <header className="biz-head"><h1>{items.find((i) => i[0] === sec)?.[2]}</h1></header>
        {sec === 'overview' && <Overview />}
        {sec === 'moderation' && <div className="biz-narrow"><Moderation /></div>}
        {sec === 'users' && <Users canEdit={isAdmin} />}
        {sec === 'directory' && <Directory />}
        {sec === 'audit' && <Audit />}
      </section>
    </div>
  )
}

function Overview() {
  const { t, lang } = useI18n()
  const q = useQuery({ queryKey: ['admin-overview'], queryFn: () => api<{ data: any }>('/mod/admin/overview'), retry: false })
  if (q.isLoading) return <div className="kpis">{[0, 1, 2, 3].map((i) => <div key={i} className="card kpi"><Sk h={14} w="60%" /><Sk h={28} w="40%" style={{ marginTop: 10 }} /></div>)}</div>
  if (q.isError) return <div className="card" style={{ padding: 16 }}>{errMsg(q.error)}</div>
  const d = q.data!.data
  const total = Object.values(d.incidents_by_status as Record<string, number>).reduce((a, b) => a + b, 0)
  const kpi = (label: string, n: number) => <div className="card kpi"><div className="small muted">{label}</div><div className="kpi-n">{fmtNumber(n, lang)}</div></div>
  const max = Math.max(1, ...Object.values(d.incidents_by_category as Record<string, number>))
  return (
    <>
      <div className="kpis">{kpi(t('ui.total_reports'), total)}{kpi(t('last24'), d.last_24h)}{kpi(t('verified'), d.incidents_by_status.verified ?? 0)}{kpi(t('ui.ai_assisted'), d.ai_assisted)}</div>
      <div className="biz-grid">
        <div className="card" style={{ padding: 16 }}><b>{t('category')}</b>
          {Object.entries(d.incidents_by_category as Record<string, number>).map(([k, n]) => (
            <div key={k} className="bar-row"><span className="bar-label">{CATS[k]?.icon} {CATS[k]?.[lang] ?? k}</span><div className="bar"><div style={{ width: (n / max) * 100 + '%' }} /></div><b>{fmtNumber(n, lang)}</b></div>))}
        </div>
        <div className="card" style={{ padding: 16 }}><b>{t('ui.users')}</b>
          {Object.entries(d.users_by_role as Record<string, number>).map(([k, n]) => <div key={k} className="row" style={{ padding: '8px 0', borderBottom: '1px solid var(--divider)' }}><span className="grow">{k}</span><b>{fmtNumber(n, lang)}</b></div>)}
        </div>
      </div>
    </>
  )
}

function Users({ canEdit }: { canEdit: boolean }) {
  const { t } = useI18n()
  const qc = useQueryClient(); const toast = useToast()
  const q = useQuery({ queryKey: ['admin-users'], queryFn: () => api<{ data: any[] }>('/mod/users') })
  const setRole = async (id: string, role: string) => { try { await api(`/mod/users/${id}/role`, { body: { role } }); qc.invalidateQueries({ queryKey: ['admin-users'] }); toast(t('ui.saved')) } catch (e) { toast(errMsg(e)) } }
  return (
    <div className="card table-card">
      {q.isLoading ? <ListSkeleton rows={6} /> : <table className="tbl"><thead><tr><th>{t('ui.name')}</th><th>{t('ui.email')}</th><th>{t('ui.role')}</th><th>{t('ui.reports')}</th></tr></thead>
        <tbody>{q.data?.data.map((u) => <tr key={u.id}><td>{u.name}{u.is_demo && <span className="badge badge-demo" style={{ marginLeft: 6 }}>DEMO</span>}</td><td className="muted">{u.email}</td>
          <td>{canEdit ? <select className="input sel" value={u.role} onChange={(e) => setRole(u.id, e.target.value)}>{['citizen', 'responder', 'moderator', 'admin'].map((r) => <option key={r}>{r}</option>)}</select> : u.role}</td><td>{u.reports}</td></tr>)}</tbody></table>}
    </div>
  )
}

function Directory() {
  const { t, lang } = useI18n()
  const qc = useQueryClient(); const toast = useToast()
  const q = useQuery({ queryKey: ['admin-res'], queryFn: () => api<{ data: any[] }>('/mod/admin/emergency-resources') })
  const [f, setF] = useState({ country_code: '', name: '', phone: '', category: 'national_emergency', source_name: '', source_url: '' })
  const add = async (e: React.FormEvent) => { e.preventDefault(); try { await api('/mod/admin/emergency-resources', { body: f }); qc.invalidateQueries({ queryKey: ['admin-res'] }); toast(t('ui.saved')) } catch (er) { toast(errMsg(er)) } }
  const verify = async (id: number) => { try { await api(`/mod/emergency-resources/${id}/verify`, { method: 'POST' }); qc.invalidateQueries(); toast(t('ui.saved')) } catch (e) { toast(errMsg(e)) } }
  return (
    <>
      <div className="card" style={{ padding: 12 }}><div className="small muted">{t('ui.directory_hint')}</div></div>
      <div className="card table-card">
        {q.isLoading ? <ListSkeleton rows={3} /> : <table className="tbl"><thead><tr><th>{t('ui.country')}</th><th>{t('ui.name')}</th><th>{t('ui.phone')}</th><th>{t('ui.source')}</th><th>{t('verified')}</th></tr></thead>
          <tbody>{q.data?.data.map((r) => <tr key={r.id}><td>{countryName(r.country_code, lang)}</td><td>{r.name}</td><td><b>{r.phone}</b></td><td><a href={r.source_url} target="_blank" rel="noreferrer">{r.source_name}</a></td>
            <td>{r.verified_at ? <span className="badge badge-verified">{fmtDateTime(r.verified_at, lang)}</span> : <button className="btn btn-primary small" onClick={() => verify(r.id)}>{t('ui.verify')}</button>}</td></tr>)}</tbody></table>}
      </div>
      <form className="card" style={{ padding: 16, display: 'grid', gap: 8, gridTemplateColumns: 'repeat(3, 1fr)' }} onSubmit={add}>
        <b style={{ gridColumn: '1 / -1' }}>{t('ui.add_contact')}</b>
        <input className="input" required maxLength={2} placeholder={t('ui.country') + ' (ISO, BD)'} value={f.country_code} onChange={(e) => setF({ ...f, country_code: e.target.value })} />
        <input className="input" required placeholder={t('ui.name')} value={f.name} onChange={(e) => setF({ ...f, name: e.target.value })} />
        <input className="input" required placeholder={t('ui.phone')} value={f.phone} onChange={(e) => setF({ ...f, phone: e.target.value })} />
        <input className="input" required placeholder={t('ui.source')} value={f.source_name} onChange={(e) => setF({ ...f, source_name: e.target.value })} />
        <input className="input" required type="url" placeholder="https://" value={f.source_url} onChange={(e) => setF({ ...f, source_url: e.target.value })} />
        <button className="btn btn-primary">{t('ui.add_contact')}</button>
      </form>
    </>
  )
}

function Audit() {
  const { lang } = useI18n()
  const q = useQuery({ queryKey: ['admin-audit'], queryFn: () => api<{ data: any[] }>('/mod/audit-logs') })
  return <div className="card table-card">{q.isLoading ? <ListSkeleton rows={6} /> : <table className="tbl"><tbody>{q.data?.data.map((a) => <tr key={a.id}><td className="muted">{fmtDateTime(a.created_at, lang)}</td><td><code>{a.action}</code></td><td className="muted">{a.subject_type?.split('\\').pop()} #{a.subject_id}</td></tr>)}</tbody></table>}</div>
}
