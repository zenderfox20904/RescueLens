import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, errMsg, SHARE_BASE } from '../lib/api'
import { useAuth } from '../lib/auth'
import { CATS, SEVS, timeAgo, useI18n } from '../lib/i18n'
import { type Incident, type Region, ttl } from '../lib/types'
import { Avatar } from './Shell'
import { IChat, IEye, IFlag, IShare, IWhatsApp } from './Icons'
import { ListSkeleton } from './Skeleton'
import { useToast } from './Toast'

export function RegionOverlay({ regions, show = true }: { regions: Region[]; show?: boolean }) {
  return <>{regions.map((r, i) => (
    <div key={i} className="region" style={{ opacity: show ? 1 : 0, left: `${(r.box[0] / r.image_width) * 100}%`, top: `${(r.box[1] / r.image_height) * 100}%`, width: `${((r.box[2] - r.box[0]) / r.image_width) * 100}%`, height: `${((r.box[3] - r.box[1]) / r.image_height) * 100}%` }}>
      <span>{r.label}{r.confidence ? ` ${Math.round(Number(r.confidence) * 100)}%` : ''} · {r.source}</span>
    </div>))}</>
}

export function StatusBadge({ i }: { i: Pick<Incident, 'status' | 'is_stale' | 'is_demo' | 'severity'> & { ai_unverified?: boolean } }) {
  const { t, lang } = useI18n()
  return (
    <span className="row" style={{ gap: 4, flexWrap: 'wrap' }}>
      {i.is_demo && <span className="badge badge-demo">{t('demo')}</span>}
      {i.ai_unverified && <span className="badge-ai">✨ {t('ui.ai_detected_unverified')}</span>}
      <span className="badge badge-sev" style={{ background: `var(--sev-${i.severity})` }}>{SEVS[i.severity]?.[lang]}</span>
      <span className={'badge' + (i.status === 'verified' ? ' badge-verified' : '')}>{i.status === 'verified' ? '✔ ' : ''}{t(i.status as any) || i.status}</span>
      {i.is_stale && <span className="badge">⏱ {t('stale')}</span>}
    </span>
  )
}

export function shareLinks(i: Incident) {
  const url = `${SHARE_BASE}/share/incidents/${i.id}`
  return {
    url,
    // WhatsApp click to chat: https://faq.whatsapp.com/5913398998672934
    whatsapp: `https://wa.me/?text=${encodeURIComponent(`⚠️ ${i.title}\n${i.location.area_name ?? ''}\n${url}`)}`,
  }
}

function Comments({ id }: { id: string }) {
  const { t, lang } = useI18n()
  const { user } = useAuth()
  const qc = useQueryClient()
  const toast = useToast()
  const [body, setBody] = useState('')
  const q = useQuery({ queryKey: ['comments', id], queryFn: () => api<{ data: { id: number; body: string; author: string; author_role: string; created_at: string }[] }>(`/incidents/${id}/comments`), refetchInterval: 8000 })
  const m = useMutation({
    mutationFn: () => api(`/incidents/${id}/comments`, { body: { body } }),
    onSuccess: () => { setBody(''); qc.invalidateQueries({ queryKey: ['comments', id] }); qc.invalidateQueries({ queryKey: ['feed'] }) },
    onError: (e) => toast(errMsg(e)),
  })
  return (
    <div style={{ paddingBottom: 8 }}>
      {q.isLoading ? <ListSkeleton rows={2} /> : q.data?.data.map((c) => (
        <div key={c.id} className="comment fade-in"><Avatar name={c.author} size={32} /><div><div className="bubble"><b className="small">{c.author}</b>{['moderator', 'admin'].includes(c.author_role) && <span className="badge badge-verified" style={{ marginLeft: 6 }}>{t('mod')}</span>}<div>{c.body}</div></div><span className="small muted" style={{ marginLeft: 12 }}>{timeAgo(c.created_at, lang)}</span></div></div>
      ))}
      {user ? (
        <form className="comment" onSubmit={(e) => { e.preventDefault(); if (body.trim()) m.mutate() }}>
          <Avatar name={user.name} size={32} /><input className="pill-input" value={body} maxLength={1000} onChange={(e) => setBody(e.target.value)} placeholder={t('writeComment')} />
        </form>) : <Link to="/login" className="small">{t('login')}</Link>}
    </div>
  )
}

export function PostCard({ i, detail = false }: { i: Incident; detail?: boolean }) {
  const { t, lang } = useI18n()
  const { user } = useAuth()
  const nav = useNavigate()
  const qc = useQueryClient()
  const toast = useToast()
  const [showComments, setShowComments] = useState(detail)
  const [showRegions, setShowRegions] = useState(true)
  const [shareOpen, setShareOpen] = useState(false)
  const react = useMutation({
    mutationFn: (type: 'confirm' | 'dispute' | 'none') => api(`/incidents/${i.id}/reactions`, { body: { type } }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['feed'] }); qc.invalidateQueries({ queryKey: ['incident', i.id] }) },
    onError: (e) => toast(errMsg(e)),
  })
  const act = (type: 'confirm' | 'dispute') => { if (!user) return nav('/login'); react.mutate(i.my_reaction === type ? 'none' : type) }
  const cat = CATS[i.category]
  const img = i.media[0]
  const regions = i.analysis?.evidence_regions ?? []
  const s = shareLinks(i)
  return (
    <article className="card post fade-in">
      <div className="post-head">
        <Avatar name={i.reporter.anonymous ? '?' : i.reporter.name} />
        <div className="grow">
          <b>{i.reporter.name ?? (t('ui.anonymous'))}</b> <span className="muted">· {cat?.icon} {cat?.[lang]}</span>
          <div className="small muted row" style={{ gap: 4 }}><Link to={`/incidents/${i.id}`} className="muted">{timeAgo(i.created_at, lang)}</Link> · 📍 {i.location.area_name ?? (t('ui.approximate_location'))}</div>
        </div>
        <StatusBadge i={i} />
      </div>
      <Link to={`/incidents/${i.id}`} style={{ color: 'inherit' }}><div className="post-title">{ttl(i)}</div></Link>
      {i.description && <div style={{ whiteSpace: 'pre-wrap' }}>{i.description_translated || i.description}</div>}
      {i.analysis && <div className="small muted" style={{ marginTop: 6 }}>✨ {t('aiSuggestion')}{i.analysis.mode === 'demo' ? ' (DEMO)' : ''}: {i.analysis.hazards.map((h) => CATS[h]?.[lang] ?? h).join(', ')}</div>}
      {img && (
        <div className="post-media" onClick={() => setShowRegions((v) => !v)}>
          <div style={{ position: 'relative', width: 'fit-content', margin: '0 auto' }}>
            <img src={img.url} alt={i.title} loading="lazy" decoding="async" width={img.width} height={img.height} style={{ height: 'auto' }} />
            <RegionOverlay regions={regions} show={showRegions} />
          </div>
        </div>
      )}
      <div className="post-stats">
        <span>👁️ {i.counts.confirm} {t('ui.confirmed')}{i.counts.dispute ? ` · ${i.counts.dispute} ${t('ui.disputed')}` : ''}</span>
        <button className="btn btn-ghost small" style={{ padding: 0 }} onClick={() => setShowComments((v) => !v)}>{i.counts.comments} {t('comment')}</button>
      </div>
      <div className="post-actions">
        <button className={'btn' + (i.my_reaction === 'confirm' ? ' on' : '')} onClick={() => act('confirm')} disabled={react.isPending || i.reporter.is_me}><IEye size={18} /> {t('iSee')}</button>
        <button className="btn" onClick={() => setShowComments((v) => !v)}><IChat size={18} /> {t('comment')}</button>
        <div style={{ flex: 1, position: 'relative' }}>
          <button className="btn" style={{ width: '100%' }} onClick={() => setShareOpen((v) => !v)}><IShare size={18} /> {t('share')}</button>
          {shareOpen && (
            <div className="card dropdown fade-in" style={{ width: 240, top: 40 }} onMouseLeave={() => setShareOpen(false)}>
              {typeof navigator.share === 'function' && <button className="notif btn-ghost" style={{ border: 0, width: '100%', background: 'none' }} onClick={() => { navigator.share({ title: ttl(i), url: s.url }).catch(() => {}); setShareOpen(false) }}><IShare /> {t('share')}</button>}
              <a className="notif" href={s.whatsapp} target="_blank" rel="noopener noreferrer"><IWhatsApp /> {t('ui.send_on_whatsapp')}</a>
              <button className="notif btn-ghost" style={{ border: 0, width: '100%', background: 'none' }} onClick={() => { navigator.clipboard?.writeText(s.url); toast(t('ui.link_copied')); setShareOpen(false) }}>🔗 {t('ui.copy_link')}</button>
            </div>)}
        </div>
        {!i.reporter.is_me && <button className={'btn' + (i.my_reaction === 'dispute' ? ' on' : '')} style={{ flex: 'none' }} title={t('notThere')} onClick={() => act('dispute')}><IFlag size={18} /></button>}
      </div>
      {showComments && <><div className="divider" style={{ margin: 0 }} /><Comments id={i.id} /></>}
    </article>
  )
}
