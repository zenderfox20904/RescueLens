import { LayoutGrid } from 'lucide-react'
import { useEffect } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { useInfiniteQuery, useQuery } from '@tanstack/react-query'
import { api } from '../lib/api'
import { useAuth } from '../lib/auth'
import { CATS, useI18n } from '../lib/i18n'
import type { Incident, MapPoint } from '../lib/types'
import { PostCard } from '../components/PostCard'
import { HomeTop } from '../components/HomeTop'
import { PostSkeleton } from '../components/Skeleton'
import { Avatar } from '../components/Shell'
import { ICamera, IPin } from '../components/Icons'

export default function Feed() {
  const { t, lang } = useI18n()
  const { user } = useAuth()
  const [sp, setSp] = useSearchParams()
  const category = sp.get('category') || ''
  const mine = sp.get('mine') === '1'
  const q = (sp.get('q') || '').toLowerCase()
  const params = new URLSearchParams({ per_page: '10', ...(category && { category }), ...(mine && { mine: '1' }) })
  const feed = useInfiniteQuery({
    queryKey: ['feed', params.toString()],
    queryFn: ({ pageParam }) => api<{ data: Incident[]; meta: { next_cursor: string | null } }>(`/incidents?${params}${pageParam ? '&cursor=' + pageParam : ''}`),
    initialPageParam: '' as string,
    getNextPageParam: (last) => last.meta.next_cursor || undefined,
    refetchInterval: 10000, // live feed polling
  })
  const live = useQuery({ queryKey: ['map', 48], queryFn: () => api<{ data: MapPoint[] }>('/incidents/map?hours=48'), refetchInterval: 15000 })
  useEffect(() => {
    const el = document.getElementById('feed-end')
    if (!el) return
    const io = new IntersectionObserver((e) => { if (e[0].isIntersecting && feed.hasNextPage && !feed.isFetchingNextPage) feed.fetchNextPage() })
    io.observe(el); return () => io.disconnect()
  }, [feed.hasNextPage, feed.isFetchingNextPage])
  const items = (feed.data?.pages.flatMap((p) => p.data) ?? []).filter((i) => !q || (i.title + ' ' + (i.description ?? '') + ' ' + (i.location.area_name ?? '')).toLowerCase().includes(q))
  return (
    <div className="main">
      <HomeTop points={live.data?.data} loading={live.isLoading} />
      <div className="card composer">
        <div className="row"><Avatar name={user?.name} size={40} /><Link to={user ? '/report' : '/login'} className="pill-input muted" style={{ padding: '10px 14px' }}>{t('whatsHappening')}</Link></div>
        <div className="divider" />
        <div className="row"><Link to="/report" className="btn btn-ghost grow"><span style={{ color: '#45BD62' }}><ICamera /></span> {t('photo')}</Link><Link to="/map" className="btn btn-ghost grow"><span style={{ color: '#F5533D' }}><IPin /></span> {t('map')}</Link></div>
      </div>
      <div className="chips">
        <button className={'chip' + (!category ? ' on' : '')} onClick={() => setSp({})}><LayoutGrid size={16} aria-hidden /> {t('all')}</button>
        {Object.entries(CATS).map(([k, v]) => <button key={k} className={'chip' + (category === k ? ' on' : '')} onClick={() => setSp({ category: k })}>{v.icon} {v[lang]}</button>)}
      </div>
      {feed.isLoading ? <><PostSkeleton /><PostSkeleton /></> : feed.isError ? <div className="card" style={{ padding: 16 }}>{t('ui.could_not_load_the_feed')}</div>
        : items.length === 0 ? <div className="card muted" style={{ padding: 24, textAlign: 'center' }}>{t('ui.no_reports_yet')}</div>
        : items.map((i) => <PostCard key={i.id} i={i} />)}
      {feed.isFetchingNextPage && <PostSkeleton />}
      <div id="feed-end" style={{ height: 1 }} />
    </div>
  )
}
