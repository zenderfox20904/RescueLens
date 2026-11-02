import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { api, errMsg } from '../lib/api'
import { CATS, SEVS, useI18n } from '../lib/i18n'
import type { Incident } from '../lib/types'
import { useToast } from './Toast'
import { useConfirm } from './Confirm'
import { Ellipsis, Pencil, Save, Trash2, X } from 'lucide-react'

/** Three dot menu on a post. Only rendered when the viewer owns it or is a moderator/admin (server sets can_manage). */
export function PostMenu({ i, detail }: { i: Incident; detail?: boolean }) {
  const { t } = useI18n()
  const [open, setOpen] = useState(false)
  const [edit, setEdit] = useState(false)
  const qc = useQueryClient()
  const toast = useToast()
  const confirm = useConfirm()
  const nav = useNavigate()
  if (!i.can_manage) return null
  const refresh = () => { qc.invalidateQueries({ queryKey: ['feed'] }); qc.invalidateQueries({ queryKey: ['incident', i.id] }); qc.invalidateQueries({ queryKey: ['trash'] }) }
  const trash = async () => {
    setOpen(false)
    if (!(await confirm({ title: t('ui.delete_post_q'), body: t('ui.delete_post_body'), confirm: t('ui.move_to_trash') }))) return
    try {
      await api(`/incidents/${i.id}`, { method: 'DELETE' }); refresh()
      if (detail) nav('/feed')
      toast(t('ui.post_trashed'), { label: t('ui.undo'), onClick: async () => { await api(`/incidents/${i.id}/restore`, { method: 'POST' }).catch(() => {}); refresh(); toast(t('ui.restored')) } })
    } catch (e) { toast(errMsg(e)) }
  }
  return <>
    <div className="kebab" data-testid="post-menu"><button aria-label={t('ui.more')} onClick={() => setOpen((v) => !v)}><Ellipsis size={18} /></button>
      {open && <div className="card menu fade-in" onMouseLeave={() => setOpen(false)}>
        <button onClick={() => { setOpen(false); setEdit(true) }}><Pencil size={16} /> {t('ui.edit_post')}</button>
        <button className="danger" onClick={trash}><Trash2 size={16} /> {t('ui.move_to_trash')}</button>
      </div>}
    </div>
    {edit && <EditPost i={i} onClose={(saved) => { setEdit(false); if (saved) { refresh(); toast(t('ui.saved')) } }} />}
  </>
}

function EditPost({ i, onClose }: { i: Incident; onClose: (saved: boolean) => void }) {
  const { t, lang } = useI18n()
  const [f, setF] = useState({ title: i.title.replace('[DEMO] ', ''), description: i.description ?? '', category: i.category, severity: i.severity })
  const [busy, setBusy] = useState(false)
  const [err, setErr] = useState('')
  const bad = f.title.trim().length < 3
  const save = async () => {
    if (bad) return
    setBusy(true)
    try { await api(`/incidents/${i.id}`, { method: 'PATCH', body: f }); onClose(true) } catch (e) { setErr(errMsg(e)); setBusy(false) }
  }
  return <div className="modal-bg" onClick={() => onClose(false)}>
    <div className="card modal fade-in" role="dialog" aria-modal="true" onClick={(e) => e.stopPropagation()}>
      <div className="modal-head">{t('ui.edit_post')}<button className="btn btn-ghost" style={{ position: 'absolute', right: 8, top: 8 }} aria-label={t('ui.cancel')} onClick={() => onClose(false)}><X size={18} /></button></div>
      <div style={{ padding: 16, display: 'grid', gap: 12 }}>
        <label><b className="small">{t('ui.title')}</b><input className={'input' + (bad ? ' invalid' : '')} value={f.title} maxLength={160} onChange={(e) => setF({ ...f, title: e.target.value })} /></label>
        <label><b className="small">{t('ui.description')}</b><textarea className="input" rows={5} maxLength={2000} value={f.description} onChange={(e) => setF({ ...f, description: e.target.value })} /></label>
        <div><b className="small">{t('ui.category')}</b><div className="chips" style={{ marginTop: 6 }}>{Object.keys(CATS).map((c) => <button type="button" key={c} className={'chip' + (f.category === c ? ' on' : '')} onClick={() => setF({ ...f, category: c as any })}>{CATS[c].icon} {CATS[c][lang]}</button>)}</div></div>
        <div><b className="small">{t('ui.severity')}</b><div className="chips" style={{ marginTop: 6 }}>{Object.keys(SEVS).map((s) => <button type="button" key={s} className={'chip' + (f.severity === s ? ' on' : '')} onClick={() => setF({ ...f, severity: s as any })}><span style={{ display: 'inline-block', width: 10, height: 10, borderRadius: 5, background: `var(--sev-${s})`, marginRight: 6 }} />{SEVS[s][lang]}</button>)}</div></div>
        {err && <div className="field-err">{err}</div>}
        <div className="confirm-actions"><button className="btn" onClick={() => onClose(false)}>{t('ui.cancel')}</button><button className="btn btn-primary" disabled={busy || bad} onClick={save}><Save size={16} aria-hidden /> {t('ui.save')}</button></div>
      </div>
    </div>
  </div>
}
