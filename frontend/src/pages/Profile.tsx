import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { useInfiniteQuery, useQuery } from '@tanstack/react-query'
import { PostCard } from '../components/PostCard'
import { PostSkeleton } from '../components/Skeleton'
import type { Incident } from '../lib/types'
import { useAuth } from '../lib/auth'
import { api } from '../lib/api'
import { useI18n } from '../lib/i18n'
import { Avatar } from '../components/Shell'
import { Sk } from '../components/Skeleton'
import { Pencil, Settings, User, Calendar, FileText } from 'lucide-react'

type Pub = { id: string; name: string; role: string; avatar_url: string | null; joined_at: string | null; reports: number; is_me: boolean }

/** Facebook style profile: header, then the person's reports as full feed cards with infinite scroll. */
export default function Profile() {
  const { id } = useParams()
  const { user, loading } = useAuth()
  const { t, lang } = useI18n()
  const [tab, setTab] = useState<'posts' | 'about'>('posts')
  const uid = id ?? (user ? String(user.id) : '')
  const head = useQuery({ queryKey: ['profile', uid], enabled: !!uid, queryFn: () => api<{ data: Pub }>(`/users/${uid}/profile`) })
  const feed = useInfiniteQuery({
    queryKey: ['feed', 'user', uid], enabled: !!uid,
    queryFn: ({ pageParam }) => api<{ data: Incident[]; meta: { next_cursor: string | null } }>(`/incidents?user=${uid}&per_page=10&lang=${lang}${pageParam ? '&cursor=' + pageParam : ''}`),
    initialPageParam: '' as string, getNextPageParam: (l) => l.meta.next_cursor || undefined,
  })
  useEffect(() => {
    const el = document.getElementById('profile-end'); if (!el) return
    const io = new IntersectionObserver((e) => { if (e[0].isIntersecting && feed.hasNextPage && !feed.isFetchingNextPage) feed.fetchNextPage() })
    io.observe(el); return () => io.disconnect()
  }, [feed.hasNextPage, feed.isFetchingNextPage])
  if ((loading && !id) || head.isLoading) return <div className="main"><div className="card" style={{ padding: 16 }}><div className="row"><Sk w={96} h={96} r={48} /><div className="grow"><Sk w="50%" h={20} /><Sk w="30%" h={12} style={{ marginTop: 8 }} /></div></div></div><PostSkeleton /></div>
  const p = head.data?.data
  if (!p) return <div className="main"><div className="card muted" style={{ padding: 24, textAlign: 'center' }}>{t('ui.profile_not_found')}</div></div>
  const items = feed.data?.pages.flatMap((x) => x.data) ?? []
  return (
    <div className="main">
      <div className="card" style={{ overflow: 'hidden' }} data-testid="profile-head">
        <div style={{ height: 170, background: 'linear-gradient(135deg, var(--grad-from), var(--grad-to))' }} />
        <div className="row" style={{ padding: '0 16px 16px', marginTop: -52, alignItems: 'flex-end', flexWrap: 'wrap' }}>
          <div style={{ border: '4px solid var(--card)', borderRadius: '50%', lineHeight: 0 }}>{p.avatar_url ? <img src={p.avatar_url} alt="" width={112} height={112} style={{ borderRadius: '50%', objectFit: 'cover' }} /> : <Avatar name={p.name} size={112} />}</div>
          <div className="grow" style={{ minWidth: 200 }}>
            <h1 style={{ margin: '0 0 2px' }}>{p.name}</h1>
            <div className="muted small">{t('ui.reports_count', { count: p.reports })}{p.joined_at ? ` · ${t('ui.joined', { date: new Date(p.joined_at).toLocaleDateString(lang, { month: 'long', year: 'numeric' }) })}` : ''}{['moderator', 'admin'].includes(p.role) ? ` · ${t('mod')}` : ''}</div>
          </div>
          {p.is_me && <div className="row" style={{ gap: 8 }}><Link className="btn btn-primary" to="/report">+ {t('ui.new_report')}</Link><Link className="btn" to="/settings#account"><Pencil size={16} /> {t('ui.edit_profile')}</Link><Link className="btn" to="/settings" aria-label={t('ui.settings')} title={t('ui.settings')} data-testid="profile-gear"><Settings size={18} /></Link></div>}
        </div>
        <div className="row" style={{ gap: 18, padding: '0 16px 12px' }}>
          <div><b>{p.reports}</b> <span className="muted small">{t('ui.reports')}</span></div>
          <div className="muted small">{['moderator', 'admin'].includes(p.role) ? t('mod') : p.role === 'responder' ? t('ui.responder') : t('ui.member')}</div>
        </div>
        <div className="tabs" role="tablist" style={{ borderTop: '1px solid var(--divider)', padding: '0 8px' }}>
          {(['posts', 'about'] as const).map((k) => <button key={k} role="tab" aria-selected={tab === k} className={'tab' + (tab === k ? ' active' : '')} onClick={() => setTab(k)}>{t('ui.tab_' + k)}</button>)}
        </div>
      </div>
      {tab === 'about' ? <div className="card" style={{ padding: 16, display: 'grid', gap: 10 }} data-testid="profile-about">
        <div className="row"><User size={18} /> {['moderator', 'admin'].includes(p.role) ? t('mod') : p.role === 'responder' ? t('ui.responder') : t('ui.member')}</div>
        {p.joined_at && <div className="row"><Calendar size={18} /> {t('ui.joined', { date: new Date(p.joined_at).toLocaleDateString(lang, { month: 'long', year: 'numeric' }) })}</div>}
        <div className="row"><FileText size={18} /> {t('ui.reports_count', { count: p.reports })}</div>
      </div> : <>
      {feed.isLoading ? <><PostSkeleton /><PostSkeleton /></>
        : items.length === 0 ? <div className="card" style={{ padding: 32, textAlign: 'center' }} data-testid="profile-empty"><FileText size={40} strokeWidth={1.5} style={{ color: 'var(--text-2)' }} /><b>{t('ui.no_reports_profile')}</b><div className="muted small" style={{ margin: '6px 0 14px' }}>{p.is_me ? t('ui.no_reports_profile_me') : t('ui.no_reports_profile_other')}</div>{p.is_me && <Link className="btn btn-primary" to="/report">{t('ui.new_report')}</Link>}</div>
        : items.map((i) => <PostCard key={i.id} i={i} />)}
      {feed.isFetchingNextPage && <PostSkeleton />}
      <div id="profile-end" style={{ height: 1 }} />
      </>}
    </div>
  )
}

