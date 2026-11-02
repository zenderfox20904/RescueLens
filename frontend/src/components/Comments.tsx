import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, errMsg } from '../lib/api'
import { useAuth } from '../lib/auth'
import { timeAgo, useI18n } from '../lib/i18n'
import { Avatar } from './Shell'
import { ListSkeleton } from './Skeleton'
import { useToast } from './Toast'
import { useConfirm } from './Confirm'
import { ThumbsUp, Pencil, Trash2 } from 'lucide-react'

export type Cmt = { id: number; parent_id: number | null; body: string; body_translated?: string; author: string; author_role: string; author_avatar?: string | null; is_mine: boolean; can_manage: boolean; likes: number; liked: boolean; edited_at?: string | null; created_at: string }

/** Text that clamps to a few lines with a See more toggle. */
export function SeeMore({ text, lines = 3 }: { text: string; lines?: number }) {
  const { t } = useI18n()
  const [open, setOpen] = useState(false)
  const long = text.length > lines * 90 || text.split('\n').length > lines
  return <div>
    <div style={{ whiteSpace: 'pre-wrap', ...(open || !long ? {} : { display: '-webkit-box', WebkitLineClamp: lines, WebkitBoxOrient: 'vertical', overflow: 'hidden' }) }}>{text}</div>
    {long && <button type="button" className="see-more" onClick={() => setOpen((v) => !v)}>{open ? t('ui.see_less') : t('ui.see_more')}</button>}
  </div>
}

function Face({ name, url, size = 32 }: { name: string; url?: string | null; size?: number }) {
  return url ? <img src={url} alt="" width={size} height={size} style={{ borderRadius: '50%', objectFit: 'cover', flex: 'none' }} /> : <Avatar name={name} size={size} />
}

/**
 * Facebook style comments: preview shows the latest two with "See more comments", full mode shows everything.
 * Threaded one level replies; like; edit and delete own (moderators and admins any) with confirmation and Undo.
 */
export function Comments({ id, preview = false }: { id: string; preview?: boolean }) {
  const { t, lang } = useI18n()
  const { user } = useAuth()
  const qc = useQueryClient()
  const toast = useToast()
  const confirm = useConfirm()
  const [all, setAll] = useState(!preview)
  const [body, setBody] = useState('')
  const [replyTo, setReplyTo] = useState<Cmt | null>(null)
  const [editing, setEditing] = useState<number | null>(null)
  const [draft, setDraft] = useState('')
  const key = ['comments', id, all ? 'all' : 'preview']
  const q = useQuery({ queryKey: key, queryFn: () => api<{ data: Cmt[]; total: number }>(`/incidents/${id}/comments?lang=${lang}${all ? '' : '&limit=2'}`), refetchInterval: 8000 })
  const refresh = () => { qc.invalidateQueries({ queryKey: ['comments', id] }); qc.invalidateQueries({ queryKey: ['feed'] }); qc.invalidateQueries({ queryKey: ['incident', id] }) }
  const add = useMutation({
    mutationFn: () => api(`/incidents/${id}/comments`, { body: { body, parent_id: replyTo?.id } }),
    onSuccess: () => { setBody(''); setReplyTo(null); refresh() }, onError: (e) => toast(errMsg(e)),
  })
  const save = async (c: Cmt) => { try { await api(`/comments/${c.id}`, { method: 'PATCH', body: { body: draft } }); setEditing(null); toast(t('ui.saved')); refresh() } catch (e) { toast(errMsg(e)) } }
  const like = async (c: Cmt) => { if (!user) return; try { await api(`/comments/${c.id}/like`, { method: 'POST' }); refresh() } catch (e) { toast(errMsg(e)) } }
  const del = async (c: Cmt) => {
    if (!(await confirm({ title: t('ui.delete_comment_q'), body: t('ui.delete_comment_body'), confirm: t('ui.delete') }))) return
    try {
      await api(`/comments/${c.id}`, { method: 'DELETE' }); refresh()
      toast(t('ui.comment_deleted'), { label: t('ui.undo'), onClick: async () => { await api(`/comments/${c.id}/restore`, { method: 'POST' }).catch(() => {}); refresh() } })
    } catch (e) { toast(errMsg(e)) }
  }
  const rows = q.data?.data ?? []
  const tops = rows.filter((c) => !c.parent_id)
  const one = (c: Cmt) => (
    <div key={c.id} className="comment fade-in" data-testid="comment">
      <Face name={c.author} url={c.author_avatar} size={c.parent_id ? 26 : 32} />
      <div className="grow" style={{ minWidth: 0 }}>
        {editing === c.id ? (
          <form onSubmit={(e) => { e.preventDefault(); if (draft.trim()) save(c) }}>
            <textarea className="input edit-area" value={draft} maxLength={1000} autoFocus onChange={(e) => setDraft(e.target.value)} />
            <div className="row" style={{ gap: 8, marginTop: 6 }}><button className="btn btn-primary small">{t('ui.save')}</button><button type="button" className="btn small" onClick={() => setEditing(null)}>{t('ui.cancel')}</button></div>
          </form>
        ) : (
          <div className="row" style={{ alignItems: 'flex-start', gap: 4 }}>
            <div className="bubble"><b className="small">{c.author}</b>{['moderator', 'admin'].includes(c.author_role) && <span className="badge badge-verified" style={{ marginLeft: 6 }}>{t('mod')}</span>}<SeeMore text={c.body_translated || c.body} lines={4} /></div>
            {c.can_manage && <CommentMenu onEdit={() => { setEditing(c.id); setDraft(c.body) }} onDelete={() => del(c)} canEdit={c.is_mine} />}
          </div>
        )}
        <div className="c-actions">
          <span className="muted" style={{ fontWeight: 400 }}>{timeAgo(c.created_at, lang)}{c.edited_at ? ` · ${t('ui.edited')}` : ''}</span>
          {user && <button className={c.liked ? 'on' : ''} onClick={() => like(c)}>{t('ui.like')}{c.likes ? ` · ${c.likes}` : ''}</button>}
          {!user && c.likes > 0 && <span><ThumbsUp size={12} /> {c.likes}</span>}
          {user && <button onClick={() => { setReplyTo(c.parent_id ? rows.find((x) => x.id === c.parent_id) ?? c : c); setAll(true) }}>{t('ui.reply')}</button>}
        </div>
      </div>
    </div>)
  return (
    <div style={{ paddingBottom: 8 }}>
      {q.isLoading ? <ListSkeleton rows={2} /> : <>
        {!all && (q.data?.total ?? 0) > rows.length && <button className="see-more" style={{ margin: '8px 12px 0' }} onClick={() => setAll(true)}>{t('ui.more_comments')}</button>}
        {tops.map((c) => <div key={c.id}>{one(c)}<div className="c-replies">{rows.filter((r) => r.parent_id === c.id).map(one)}</div></div>)}
      </>}
      {user ? (
        <form className="comment" onSubmit={(e) => { e.preventDefault(); if (body.trim()) add.mutate() }}>
          <Face name={user.name} url={(user as any).avatar_url} />
          <div className="grow">
            {replyTo && <div className="small muted" style={{ margin: '0 0 4px 12px' }}>{t('ui.replying_to', { name: replyTo.author })} · <button type="button" className="see-more small" onClick={() => setReplyTo(null)}>{t('ui.cancel')}</button></div>}
            <input className="pill-input" style={{ width: '100%' }} value={body} maxLength={1000} onChange={(e) => setBody(e.target.value)} placeholder={replyTo ? t('ui.write_reply') : t('writeComment')} />
          </div>
        </form>) : <Link to="/login" className="small">{t('login')}</Link>}
    </div>
  )
}

function CommentMenu({ onEdit, onDelete, canEdit }: { onEdit: () => void; onDelete: () => void; canEdit: boolean }) {
  const { t } = useI18n()
  const [open, setOpen] = useState(false)
  return <div className="kebab"><button aria-label={t('ui.more')} onClick={() => setOpen((v) => !v)}>⋯</button>
    {open && <div className="card menu fade-in" onMouseLeave={() => setOpen(false)}>
      {canEdit && <button onClick={() => { setOpen(false); onEdit() }}><Pencil size={16} /> {t('ui.edit')}</button>}
      <button className="danger" onClick={() => { setOpen(false); onDelete() }}><Trash2 size={16} /> {t('ui.delete')}</button>
    </div>}</div>
}
