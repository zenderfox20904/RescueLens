import { useNavigate, useParams } from 'react-router-dom'
import { ENTITIES, entityByKey } from './admin/entities'
import { EntityAdmin } from './admin/EntityAdmin'
import { useQuery } from '@tanstack/react-query'
import { api, errMsg } from '../lib/api'
import { CATS, fmtDateTime, fmtNumber, useI18n } from '../lib/i18n'
import { useAuth } from '../lib/auth'
import { ListSkeleton, Sk } from '../components/Skeleton'
import { IChart, IGlobe, IShield, IFlag } from '../components/Icons'
import Moderation from './Moderation'

/** System admin panel: left rail, page header, KPI tiles, and one data table per managed entity (deep linkable). */
export default function Admin() {
  const { t } = useI18n()
  const { user } = useAuth()
  const { section = 'overview' } = useParams()
  const nav = useNavigate()
  const isAdmin = user?.role === 'admin'
  const items: [string, React.ReactNode, string, boolean][] = [
    ['overview', <IChart size={20} />, t('ui.overview'), true],
    ['moderation', <IShield size={20} />, t('mod'), true],
    ...ENTITIES.map((e) => [e.key, <span className="rail-emoji" aria-hidden>{e.icon}</span>, t(e.label), !e.adminOnly || isAdmin] as [string, React.ReactNode, string, boolean]),
    ['audit', <IFlag size={20} />, t('ui.audit_log'), isAdmin],
  ]
  const ent = entityByKey(section)
  const allowed = items.find((i) => i[0] === section)?.[3]
  return (
    <div className="biz">
      <aside className="biz-rail">
        <div className="biz-brand"><IGlobe size={22} /><div><b>RescueLens</b><div className="small muted">{t('ui.admin_panel')}</div></div></div>
        {items.filter((i) => i[3]).map(([k, icon, label]) => (
          <button key={k} className={'biz-item' + (section === k ? ' on' : '')} onClick={() => nav(k === 'overview' ? '/admin' : `/admin/${k}`)} data-testid={`rail-${k}`}>{icon}<span>{label}</span></button>))}
      </aside>
      <section className="biz-main">
        <header className="biz-head"><h1>{items.find((i) => i[0] === section)?.[2] ?? t('ui.not_found')}</h1></header>
        {!allowed ? <div className="card" style={{ padding: 16 }}>{t('ui.not_found')}</div>
          : section === 'overview' ? <Overview />
          : section === 'moderation' ? <div className="biz-narrow"><Moderation /></div>
          : section === 'audit' ? <Audit />
          : ent ? <EntityAdmin ent={ent} /> : null}
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

function Audit() {
  const { lang } = useI18n()
  const q = useQuery({ queryKey: ['admin-audit'], queryFn: () => api<{ data: any[] }>('/mod/audit-logs') })
  return <div className="card table-card">{q.isLoading ? <ListSkeleton rows={6} /> : <table className="tbl"><tbody>{q.data?.data.map((a) => <tr key={a.id}><td className="muted">{fmtDateTime(a.created_at, lang)}</td><td><code>{a.action}</code></td><td className="muted">{a.subject_type?.split('\\').pop()} #{a.subject_id}</td></tr>)}</tbody></table>}</div>
}
