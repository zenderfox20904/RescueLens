import { useEffect, useMemo, useState } from 'react'
import { keepPreviousData, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { api, errMsg } from '../../lib/api'
import { useAuth } from '../../lib/auth'
import { CATS, SEVS, fmtDateTime, fmtNumber, useI18n } from '../../lib/i18n'
import { Sk } from '../../components/Skeleton'
import { useToast } from '../../components/Toast'
import { LocationPicker, type PickedLocation } from '../../components/location/LocationPicker'
import type { Col, Entity, Field } from './entities'

type Row = Record<string, any> & { id: string }
type ListRes = { data: Row[]; meta: { total: number; page: number; last_page: number; per_page: number; trash_count?: number } }

function useDebounced<T>(v: T, ms = 300) { const [d, setD] = useState(v); useEffect(() => { const h = setTimeout(() => setD(v), ms); return () => clearTimeout(h) }, [v, ms]); return d }

/**
 * One data table for every managed entity: search, filters, sort, pagination, shimmer skeletons, locale formatted values.
 * Row click opens a deep linked details drawer (/admin/{entity}/{id}); inline View, Edit and Delete on every row.
 * Edit is optimistic; Delete asks once, moves the item to Trash and offers Undo. Trash restores or deletes forever.
 */
export function EntityAdmin({ ent }: { ent: Entity }) {
  const { t, lang } = useI18n()
  const { id } = useParams()
  const nav = useNavigate()
  const qc = useQueryClient(); const toast = useToast()
  const [tab, setTab] = useState<'active' | 'trash'>('active')
  const [q, setQ] = useState('')
  const dq = useDebounced(q)
  const [filters, setFilters] = useState<Record<string, string>>({})
  const [sort, setSort] = useState<{ col: string; dir: 'asc' | 'desc' }>({ col: ent.defaultSort ?? 'created_at', dir: ent.defaultSort === 'name' || ent.defaultSort === 'sort' ? 'asc' : 'desc' })
  const [page, setPage] = useState(1)
  const [editing, setEditing] = useState<Row | 'new' | null>(null)
  const [confirm, setConfirm] = useState<{ row: Row; force: boolean } | null>(null)
  useEffect(() => { setTab('active'); setQ(''); setFilters({}); setPage(1); setSort({ col: ent.defaultSort ?? 'created_at', dir: ent.defaultSort === 'name' || ent.defaultSort === 'sort' ? 'asc' : 'desc' }) }, [ent.key])
  useEffect(() => setPage(1), [dq, filters, tab])

  const params = useMemo(() => {
    const p = new URLSearchParams({ page: String(page), per_page: '15', sort: sort.col, dir: sort.dir, lang })
    if (dq) p.set('q', dq)
    if (tab === 'trash') p.set('trashed', 'only')
    Object.entries(filters).forEach(([k, v]) => v && p.set(`filter[${k}]`, v))
    return p.toString()
  }, [page, sort, dq, tab, filters, lang])
  const key = ['crud', ent.key, params]
  const list = useQuery({ queryKey: key, queryFn: () => api<ListRes>(`/crud/${ent.key}?${params}`), placeholderData: keepPreviousData, refetchInterval: 20000 })
  const rows = list.data?.data ?? []
  const meta = list.data?.meta

  const open = (r: Row) => nav(`/admin/${ent.key}/${encodeURIComponent(r.id)}`)
  const patchLists = (fn: (rows: Row[]) => Row[]) => {
    const snap = qc.getQueriesData<ListRes>({ queryKey: ['crud', ent.key] })
    qc.setQueriesData<ListRes>({ queryKey: ['crud', ent.key] }, (d) => d && { ...d, data: fn(d.data) })
    return () => snap.forEach(([k, v]) => qc.setQueryData(k, v))
  }
  const refresh = () => qc.invalidateQueries({ queryKey: ['crud', ent.key] })

  const save = async (row: Row | 'new', body: Record<string, any>) => {
    if (row === 'new') {
      const r = await api<{ data: Row }>(ent.createPath ?? `/crud/${ent.key}`, { body })
      toast(t('ui.created_ok')); refresh(); setEditing(null); if (r.data?.id) open(r.data)
      return
    }
    // Optimistic: the row updates immediately, and rolls back if the server refuses.
    const rollback = patchLists((rs) => rs.map((x) => (x.id === row.id ? { ...x, ...body } : x)))
    qc.setQueryData(['crud-one', ent.key, row.id], (d: any) => d && { data: { ...d.data, ...body } })
    setEditing(null)
    try {
      const r = await api<{ data: Row }>(ent.editPath ? ent.editPath(row.id) : `/crud/${ent.key}/${encodeURIComponent(row.id)}`, { method: 'PATCH', body })
      patchLists((rs) => rs.map((x) => (x.id === row.id ? { ...x, ...r.data } : x)))
      toast(t('ui.saved'))
    } catch (e) { rollback(); qc.invalidateQueries({ queryKey: ['crud-one', ent.key, row.id] }); toast(errMsg(e)) }
  }

  const restore = async (row: Row) => {
    try { await api(`/crud/${ent.key}/${encodeURIComponent(row.id)}/restore`, { method: 'POST' }); toast(t('ui.restored')); refresh(); qc.invalidateQueries({ queryKey: ['crud-one', ent.key, row.id] }) } catch (e) { toast(errMsg(e)) }
  }
  const doDelete = async ({ row, force }: { row: Row; force: boolean }) => {
    setConfirm(null)
    const rollback = patchLists((rs) => rs.filter((x) => x.id !== row.id))
    if (id === row.id) nav(`/admin/${ent.key}`)
    try {
      await api(`/crud/${ent.key}/${encodeURIComponent(row.id)}${force ? '/force' : ''}`, { method: 'DELETE' })
      if (force) toast(t('ui.deleted_forever'))
      else toast(t('ui.moved_to_trash'), { label: t('ui.undo'), onClick: () => restore(row) })
      refresh()
    } catch (e) { rollback(); toast(errMsg(e)) }
  }

  const sortBy = (c: Col) => c.sort && setSort((s) => ({ col: c.sort!, dir: s.col === c.sort && s.dir === 'asc' ? 'desc' : 'asc' }))
  const canWrite = !!ent.edit

  return (
    <div className="ent" data-testid={`entity-${ent.key}`}>
      <div className="ent-bar">
        <div className="seg" role="tablist">
          <button role="tab" aria-selected={tab === 'active'} className={tab === 'active' ? 'on' : ''} onClick={() => setTab('active')}>{t('ui.all')}{tab === 'active' && meta ? ` · ${fmtNumber(meta.total, lang)}` : ''}</button>
          <button role="tab" aria-selected={tab === 'trash'} className={tab === 'trash' ? 'on' : ''} onClick={() => setTab('trash')} data-testid="trash-tab">🗑 {t('ui.trash')}{meta?.trash_count ? ` · ${fmtNumber(meta.trash_count, lang)}` : ''}</button>
        </div>
        <input className="pill-input ent-search" placeholder={t('ui.search')} value={q} onChange={(e) => setQ(e.target.value)} aria-label={t('ui.search')} />
        {Object.entries(ent.filters ?? {}).map(([k, opts]) => (
          <select key={k} className="input sel ent-filter" value={filters[k] ?? ''} onChange={(e) => setFilters({ ...filters, [k]: e.target.value })} aria-label={k}>
            <option value="">{k}: {t('ui.any')}</option>
            {opts().map(([v, l]) => <option key={v} value={v}>{l}</option>)}
          </select>
        ))}
        <span className="grow" />
        {ent.create && tab === 'active' && <button className="btn btn-primary" onClick={() => setEditing('new')} data-testid="create-btn">＋ {t('ui.create')}</button>}
      </div>

      <div className="card table-card ent-table">
        <table className="tbl">
          <thead><tr>{ent.cols.map((c) => (
            <th key={c.key} className={c.sort ? 'sortable' : ''} onClick={() => sortBy(c)} aria-sort={sort.col === c.sort ? (sort.dir === 'asc' ? 'ascending' : 'descending') : undefined}>
              {t(c.label)}{c.sort && <span className="sort-ic">{sort.col === c.sort ? (sort.dir === 'asc' ? ' ▲' : ' ▼') : ' ↕'}</span>}
            </th>))}<th className="ent-actions-h">{t('ui.actions')}</th></tr></thead>
          <tbody>
            {list.isLoading ? Array.from({ length: 6 }).map((_, i) => <tr key={i}>{ent.cols.map((c) => <td key={c.key}><Sk h={12} w={c.kind === 'date' ? 110 : '80%'} /></td>)}<td><Sk h={12} w={90} /></td></tr>)
              : rows.length === 0 ? <tr><td colSpan={ent.cols.length + 1} className="muted ent-empty">{tab === 'trash' ? t('ui.trash_empty') : t('ui.no_results')}</td></tr>
              : rows.map((r) => (
                <tr key={r.id} className={'ent-row' + (id === r.id ? ' sel' : '')} onClick={() => open(r)} data-row={r.id}>
                  {ent.cols.map((c) => <td key={c.key}><Cell c={c} v={r[c.key]} row={r} /></td>)}
                  <td className="ent-actions" onClick={(e) => e.stopPropagation()}>
                    {tab === 'active' ? <>
                      <button className="mini" onClick={() => open(r)} aria-label={t('ui.view')} title={t('ui.view')}>👁</button>
                      {canWrite && <button className="mini" onClick={() => setEditing(r)} aria-label={t('ui.edit')} title={t('ui.edit')} data-testid="row-edit">✎</button>}
                      <button className="mini danger" onClick={() => setConfirm({ row: r, force: false })} aria-label={t('ui.delete')} title={t('ui.delete')} data-testid="row-delete">🗑</button>
                    </> : <>
                      <button className="mini" onClick={() => restore(r)} title={t('ui.restore')} data-testid="row-restore">↺ {t('ui.restore')}</button>
                      <button className="mini danger" onClick={() => setConfirm({ row: r, force: true })} title={t('ui.delete_forever')}>✕</button>
                    </>}
                  </td>
                </tr>))}
          </tbody>
        </table>
      </div>
      {meta && meta.last_page > 1 && (
        <div className="ent-pager small">
          <span className="muted">{t('ui.page_of', { page: fmtNumber(meta.page, lang), pages: fmtNumber(meta.last_page, lang) })}</span>
          <button className="btn" disabled={page <= 1} onClick={() => setPage(page - 1)}>{t('ui.previous')}</button>
          <button className="btn" disabled={page >= meta.last_page} onClick={() => setPage(page + 1)}>{t('ui.next')}</button>
        </div>
      )}

      {id && <Drawer ent={ent} id={id} onClose={() => nav(`/admin/${ent.key}`)} onEdit={(r) => setEditing(r)} onDelete={(r, force) => setConfirm({ row: r, force })} onRestore={restore} />}
      {editing && <EditModal ent={ent} row={editing} onClose={() => setEditing(null)} onSave={save} />}
      {confirm && (
        <div className="modal-bg" onClick={() => setConfirm(null)}>
          <div className="card modal confirm fade-in" role="alertdialog" aria-modal="true" onClick={(e) => e.stopPropagation()}>
            <h3 className="h3">{confirm.force ? t('ui.delete_forever_q') : t('ui.move_to_trash_q')}</h3>
            <p className="muted">{ent.title(confirm.row)}</p>
            <p className="small muted">{confirm.force ? t('ui.delete_forever_hint') : t('ui.move_to_trash_hint')}</p>
            <div className="row" style={{ justifyContent: 'flex-end' }}>
              <button className="btn" onClick={() => setConfirm(null)} autoFocus>{t('cancel')}</button>
              <button className="btn btn-danger" onClick={() => doDelete(confirm)} data-testid="confirm-delete">{confirm.force ? t('ui.delete_forever') : t('ui.delete')}</button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

function Cell({ c, v, row }: { c: Col; v: any; row: Row }) {
  const { lang, t } = useI18n()
  if (v === null || v === undefined || v === '') return <span className="muted">·</span>
  switch (c.kind) {
    case 'date': return <span className="muted nowrap">{fmtDateTime(v, lang)}</span>
    case 'number': return <span className="num">{fmtNumber(+v, lang)}</span>
    case 'bool': return v ? <span className="badge badge-verified">✓</span> : <span className="badge">✕</span>
    case 'badge': return <span className="badge">{String(v).replace(/_/g, ' ')}</span>
    case 'code': return <code>{v}</code>
    case 'severity': return <span className={'sev-pill sev-pill-' + v}><i aria-hidden />{SEVS[v]?.label ?? v}</span>
    case 'category': return <span className="nowrap">{CATS[v]?.icon} {CATS[v]?.label ?? v}</span>
    default: {
      const text = c.key === 'title' ? (row.title_translated || v) : v
      return <span className="cell-text" title={String(text)}>{String(text)}{row.is_demo && c.key === ENT_TITLE[c.key] ? <span className="badge badge-demo" style={{ marginInlineStart: 6 }}>{t('ui.demo')}</span> : null}</span>
    }
  }
}
const ENT_TITLE: Record<string, string> = { title: 'title', name: 'name' }

function Drawer({ ent, id, onClose, onEdit, onDelete, onRestore }: { ent: Entity; id: string; onClose: () => void; onEdit: (r: Row) => void; onDelete: (r: Row, force: boolean) => void; onRestore: (r: Row) => void }) {
  const { t, lang } = useI18n(); const { user } = useAuth(); const toast = useToast(); const qc = useQueryClient()
  const q = useQuery({ queryKey: ['crud-one', ent.key, id], queryFn: () => api<{ data: Row }>(`/crud/${ent.key}/${encodeURIComponent(id)}?lang=${lang}`), retry: false })
  useEffect(() => { const k = (e: KeyboardEvent) => e.key === 'Escape' && onClose(); addEventListener('keydown', k); return () => removeEventListener('keydown', k) }, [onClose])
  const r = q.data?.data
  const act = async (path: string, body?: any) => { try { await api(path, { method: 'POST', body }); toast(t('ui.saved')); qc.invalidateQueries({ queryKey: ['crud'] }); qc.invalidateQueries({ queryKey: ['crud-one'] }) } catch (e) { toast(errMsg(e)) } }
  return (
    <div className="drawer-bg" onClick={onClose}>
      <aside className="drawer fade-in" role="dialog" aria-modal="true" aria-label={t('ui.details')} onClick={(e) => e.stopPropagation()} data-testid="details-drawer">
        <header className="drawer-head">
          <span className="drawer-ic">{ent.icon}</span>
          <div className="grow" style={{ minWidth: 0 }}>{q.isLoading ? <Sk h={16} w="70%" /> : <b className="drawer-title">{r ? ent.title(r) : t('ui.not_found')}</b>}<div className="small muted">{t(ent.label)} · <code>{id}</code></div></div>
          <button className="iconbtn" onClick={onClose} aria-label={t('cancel')}>✕</button>
        </header>
        {q.isLoading ? <div className="drawer-body">{Array.from({ length: 7 }).map((_, i) => <Sk key={i} h={14} style={{ marginBottom: 14 }} />)}</div>
          : q.isError || !r ? <div className="drawer-body muted">{errMsg(q.error)}</div>
          : <>
            {r.deleted_at && <div className="drawer-trash small">🗑 {t('ui.in_trash_since', { when: fmtDateTime(r.deleted_at, lang) })}</div>}
            <dl className="drawer-body kv">
              {Object.entries(r).filter(([k]) => !['deleted_at'].includes(k)).map(([k, v]) => (
                <div key={k}><dt>{k.replace(/_/g, ' ')}</dt><dd>{v === null || v === '' ? <span className="muted">·</span> : typeof v === 'boolean' ? (v ? '✓' : '✕') : /_at$/.test(k) && typeof v === 'string' ? fmtDateTime(v, lang) : typeof v === 'object' ? <code>{JSON.stringify(v)}</code> : String(v)}</dd></div>
              ))}
            </dl>
            <footer className="drawer-foot">
              {ent.key === 'incidents' && !r.deleted_at && <Link className="btn" to={`/incidents/${r.id}`}>{t('ui.open_public_page')}</Link>}
              {ent.key === 'users' && user?.role === 'admin' && !r.deleted_at && (
                <label className="row small">{t('ui.role')}<select className="input sel" value={r.role} onChange={(e) => act(`/mod/users/${r.id}/role`, { role: e.target.value })}>{['citizen', 'responder', 'moderator', 'admin'].map((x) => <option key={x}>{x}</option>)}</select></label>)}
              {ent.key === 'emergency-resources' && !r.verified && !r.deleted_at && <button className="btn btn-primary" onClick={() => act(`/mod/emergency-resources/${r.id}/verify`)}>{t('ui.verify')}</button>}
              {ent.key === 'camera-feeds' && !r.deleted_at && r.kind === 'snapshot_url' && <button className="btn" onClick={() => act(`/mod/admin/camera-feeds/${r.id}/poll`)}>{t('ui.poll_now')}</button>}
              <span className="grow" />
              {r.deleted_at ? <>
                <button className="btn" onClick={() => onRestore(r)}>↺ {t('ui.restore')}</button>
                <button className="btn btn-danger" onClick={() => onDelete(r, true)}>{t('ui.delete_forever')}</button>
              </> : <>
                {ent.edit && <button className="btn" onClick={() => onEdit(r)}>✎ {t('ui.edit')}</button>}
                <button className="btn btn-danger" onClick={() => onDelete(r, false)}>{t('ui.delete')}</button>
              </>}
            </footer>
          </>}
      </aside>
    </div>
  )
}

function EditModal({ ent, row, onClose, onSave }: { ent: Entity; row: Row | 'new'; onClose: () => void; onSave: (row: Row | 'new', body: Record<string, any>) => Promise<void> }) {
  const { t } = useI18n()
  const fields: Field[] = (row === 'new' ? ent.create : ent.edit) ?? []
  const [v, setV] = useState<Record<string, any>>(() => Object.fromEntries(fields.filter((f) => f.type !== 'location').map((f) => [f.key, row === 'new' ? (f.type === 'bool' ? true : f.options ? f.options()[0]?.[0] ?? '' : '') : row[f.key] ?? (f.type === 'bool' ? false : '')])))
  const [loc, setLoc] = useState<PickedLocation | null>(null)
  const [err, setErr] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  useEffect(() => { const k = (e: KeyboardEvent) => e.key === 'Escape' && onClose(); addEventListener('keydown', k); return () => removeEventListener('keydown', k) }, [onClose])
  const submit = async (e: React.FormEvent) => {
    e.preventDefault(); setErr(null)
    const body: Record<string, any> = {}
    for (const f of fields) {
      if (f.type === 'location') { if (loc) Object.assign(body, { lat: loc.lat, lng: loc.lng, ...(['incidents', 'organizations'].includes(ent.key) ? { address: loc.address } : {}) }); else if (f.required) { setErr(t('ui.pick_a_location')); return } continue }
      const val = v[f.key]
      if (row !== 'new' && val === (row[f.key] ?? (f.type === 'bool' ? false : ''))) continue
      if (row === 'new' && (val === '' || val === null) && !f.required) continue
      body[f.key] = f.type === 'number' ? (val === '' ? null : +val) : val
    }
    if (loc && fields.some((f) => f.key === 'area_name') && !v.area_name && loc.area_name) body.area_name = loc.area_name
    if (row !== 'new' && Object.keys(body).length === 0) { onClose(); return }
    setBusy(true)
    try { await onSave(row, body) } catch (er) { setErr(errMsg(er)) } finally { setBusy(false) }
  }
  return (
    <div className="modal-bg" onClick={onClose}>
      <form className="card modal ent-form fade-in" role="dialog" aria-modal="true" onClick={(e) => e.stopPropagation()} onSubmit={submit} data-testid="edit-modal">
        <div className="modal-head">{row === 'new' ? `${t('ui.create')} · ${t(ent.label)}` : `${t('ui.edit')} · ${ent.title(row)}`}</div>
        <div className="ent-fields">
          {fields.map((f) => (
            <label key={f.key} className={'ent-field' + (f.type === 'textarea' || f.type === 'location' ? ' wide' : '')}>
              <span className="small muted">{t(f.label)}{f.required && ' *'}</span>
              {f.type === 'location' ? <LocationPicker value={loc} onChange={setLoc} />
                : f.type === 'textarea' ? <textarea className="input" rows={4} maxLength={f.maxLength} value={v[f.key]} onChange={(e) => setV({ ...v, [f.key]: e.target.value })} required={f.required} />
                : f.type === 'select' ? <select className="input" value={v[f.key]} onChange={(e) => setV({ ...v, [f.key]: e.target.value })} required={f.required}>{f.options!().map(([o, l]) => <option key={o} value={o}>{l}</option>)}</select>
                : f.type === 'bool' ? <span className="row"><input type="checkbox" checked={!!v[f.key]} onChange={(e) => setV({ ...v, [f.key]: e.target.checked })} /> {t('ui.yes')}</span>
                : <input className="input" type={f.type ?? 'text'} maxLength={f.maxLength} value={v[f.key]} onChange={(e) => setV({ ...v, [f.key]: e.target.value })} required={f.required} autoComplete={f.type === 'password' ? 'new-password' : 'off'} />}
            </label>
          ))}
        </div>
        {err && <div className="err small" role="alert">{err}</div>}
        <div className="row ent-form-foot"><button type="button" className="btn" onClick={onClose}>{t('cancel')}</button><button className="btn btn-primary" disabled={busy} data-testid="save-btn">{busy ? '…' : t('ui.save')}</button></div>
      </form>
    </div>
  )
}
