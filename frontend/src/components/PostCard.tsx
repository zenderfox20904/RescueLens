import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { api, errMsg, SHARE_BASE } from '../lib/api'
import { useAuth } from '../lib/auth'
import { CATS, SEVS, timeAgo, useI18n } from '../lib/i18n'
import { type Incident, type Region, ttl } from '../lib/types'
import { Avatar } from './Shell'
import { IChat, IEye, IFlag, IShare, IWhatsApp } from './Icons'
import { useToast } from './Toast'
import { Comments, SeeMore } from './Comments'
import { PostMenu } from './PostManage'
import { usePostTr, togglePostTr } from '../lib/postTranslation'
import { Sparkles, Check, Clock, MapPin, Eye, Link2, Languages, MessageCircle } from 'lucide-react'

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
      {i.ai_unverified && <span className="badge-ai"><Sparkles size={12} /> {t('ui.ai_detected_unverified')}</span>}
      <span className="badge badge-sev" style={{ background: `var(--sev-${i.severity})` }}>{SEVS[i.severity]?.[lang]}</span>
      <span className={'badge' + (i.status === 'verified' ? ' badge-verified' : '')}>{i.status === 'verified' ? <Check size={12} /> : ''}{t(i.status as any) || i.status}</span>
      {i.is_stale && <span className="badge"><Clock size={12} /> {t('stale')}</span>}
    </span>
  )
}

export function shareLinks(i: Incident) {
  const url = `${SHARE_BASE}/share/incidents/${i.id}`
  return {
    url,
    // WhatsApp click to chat: https://faq.whatsapp.com/5913398998672934
    whatsapp: `https://wa.me/?text=${encodeURIComponent(`${i.title}\n${i.location.area_name ?? ''}\n${url}`)}`,
  }
}

/** Shows machine translated user content with a toggle back to the original text. */
export function Translated({ original, translated }: { original: string; translated?: string | null }) {
  const { t } = useI18n()
  const [orig, setOrig] = useState(false)
  if (!translated) return <div>{original}</div>
  return <div>{orig ? original : translated} <button type="button" className="link-btn small" onClick={() => setOrig((v) => !v)}><Languages size={13} aria-hidden style={{ verticalAlign: -2 }} /> {orig ? t('ui.see_translation') : t('ui.see_original')}</button></div>
}

export function PostCard({ i, detail = false }: { i: Incident; detail?: boolean }) {
  const { t, lang } = useI18n()
  const { user } = useAuth()
  const nav = useNavigate()
  const qc = useQueryClient()
  const toast = useToast()
  const [showComments, setShowComments] = useState(true)
  const [showRegions, setShowRegions] = useState(true)
  const [shareOpen, setShareOpen] = useState(false)
  const tr = usePostTr(i.id)
  const trOn = tr?.status === 'on' && tr.data?.lang === lang ? tr.data : null
  const react = useMutation({
    mutationFn: (type: 'confirm' | 'dispute' | 'none') => api(`/incidents/${i.id}/reactions`, { body: { type } }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['feed'] }); qc.invalidateQueries({ queryKey: ['incident', i.id] }) },
    onError: (e) => toast(errMsg(e)),
  })
  const act = (type: 'confirm' | 'dispute') => { if (!user) return nav('/login'); react.mutate(i.my_reaction === type ? 'none' : type) }
  const cat = CATS[i.category]
  const img = i.media[0]
  const [playing, setPlaying] = useState(false)
  const regions = i.analysis?.evidence_regions ?? []
  const s = shareLinks(i)
  return (
    <article className="card post fade-in">
      <div className="post-head">
        {i.reporter.avatar ? <img src={i.reporter.avatar} alt="" width={40} height={40} style={{ borderRadius: '50%', objectFit: 'cover' }} /> : <Avatar name={i.reporter.anonymous ? '?' : i.reporter.name} />}
        <div className="grow">
          {i.reporter.id ? <Link to={`/u/${i.reporter.id}`} style={{ color: 'inherit' }}><b>{i.reporter.name}</b></Link> : <b>{i.reporter.name ?? (t('ui.anonymous'))}</b>} <span className="muted">· {cat?.icon} {cat?.[lang]}</span>
          <div className="small muted row" style={{ gap: 4 }}><Link to={`/incidents/${i.id}`} className="muted">{timeAgo(i.created_at, lang)}</Link>{i.edited_at ? ` · ${t('ui.edited')}` : ''} · <MapPin size={12} style={{ verticalAlign: -1 }} /> {i.location.area_name ?? (t('ui.approximate_location'))}</div>
        </div>
        <StatusBadge i={i} />
        <PostMenu i={i} detail={detail} />
      </div>
      <Link to={`/incidents/${i.id}`} style={{ color: 'inherit' }}><div className="post-title">{trOn ? trOn.title : i.title}</div></Link>
      {i.description && (detail ? <div style={{ whiteSpace: 'pre-wrap' }}>{trOn?.description ?? i.description}</div> : <SeeMore text={trOn?.description ?? i.description} />)}
      <button type="button" className="link-btn small" disabled={tr?.status === 'loading'} onClick={() => togglePostTr(i.id, lang)}><Languages size={13} aria-hidden style={{ verticalAlign: -2 }} /> {tr?.status === 'loading' ? t('ui.translating') : trOn ? t('ui.see_original') : t('ui.see_translation')}</button>
      {tr?.status === 'error' && <span className="small muted"> {t('ui.translation_failed')}</span>}
      {trOn?.analysis?.summary && <div className="card small" style={{ marginTop: 6, padding: 10 }}><b><Sparkles size={12} /> {t('ui.meta_ai_analysis')}</b><div>{trOn.analysis.summary}</div>{!!trOn.analysis.reasons.length && <ul style={{ margin: '4px 0 0', paddingInlineStart: 18 }}>{trOn.analysis.reasons.map((r, k) => <li key={k}>{r}</li>)}</ul>}{!!trOn.analysis.precautions.length && <div><b>{t('ui.precautions')}:</b> {trOn.analysis.precautions.join(' • ')}</div>}<div className="muted">{t('ui.translated_by_meta_ai')}</div></div>}
      {i.analysis && <div className="small muted" style={{ marginTop: 6 }}><Sparkles size={12} /> {t('aiSuggestion')}{i.analysis.mode === 'demo' ? ' (DEMO)' : ''}: {i.analysis.hazards.map((h) => CATS[h]?.[lang] ?? h).join(', ')}</div>}
      {img && (
        <div className="post-media" onClick={() => setShowRegions((v) => !v)}>
          <div style={{ position: 'relative', width: 'fit-content', margin: '0 auto' }}>
            {img.video_url ? <video src={img.video_url} poster={img.url} controls muted playsInline preload="none" width={img.width} height={img.height} style={{ height: 'auto', width: '100%' }} onClick={(e) => e.stopPropagation()} onPlay={() => setPlaying(true)} onPause={() => setPlaying(false)} onEnded={() => setPlaying(false)} aria-label={i.title} />
              : <img src={img.url} alt={i.title} loading="lazy" decoding="async" width={img.width} height={img.height} style={{ height: 'auto' }} />}
            {!playing && <RegionOverlay regions={regions} show={showRegions} />}
          </div>
        </div>
      )}
      <div className="post-stats">
        <span><Eye size={14} /> {i.counts.confirm} {t('ui.confirmed')}{i.counts.dispute ? ` · ${i.counts.dispute} ${t('ui.disputed')}` : ''}</span>
        <button className="btn btn-ghost small" style={{ padding: 0 }} onClick={() => setShowComments((v) => !v)}><MessageCircle size={14} aria-hidden style={{ verticalAlign: -2 }} /> {i.counts.comments} {t('comment')}</button>
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
              <button className="notif btn-ghost" style={{ border: 0, width: '100%', background: 'none' }} onClick={() => { navigator.clipboard?.writeText(s.url); toast(t('ui.link_copied')); setShareOpen(false) }}><Link2 size={16} /> {t('ui.copy_link')}</button>
            </div>)}
        </div>
        {!i.reporter.is_me && <button className={'btn' + (i.my_reaction === 'dispute' ? ' on' : '')} style={{ flex: 'none' }} title={t('notThere')} onClick={() => act('dispute')}><IFlag size={18} /></button>}
      </div>
      {showComments && <><div className="divider" style={{ margin: 0 }} /><Comments id={i.id} preview={!detail} /></>}
    </article>
  )
}
