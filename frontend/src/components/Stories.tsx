import { Link } from 'react-router-dom'
import { CATS, timeAgo, useI18n } from '../lib/i18n'
import type { MapPoint } from '../lib/types'
import { StoriesSkeleton } from './Skeleton'

const BG: Record<string, string> = { road_accident: '#5B6B8C', flood: '#2B7BB9', fire: '#C0392B', electrical: '#B7950B', building_collapse: '#6E5848', injury: '#AD1457', other: '#455A64' }
export function Stories({ items, loading }: { items?: MapPoint[]; loading: boolean }) {
  const { t, lang } = useI18n()
  if (loading) return <StoriesSkeleton />
  return (
    <div className="stories" aria-label={t('recent')}>
      <Link to="/report" className="story card" style={{ background: 'var(--card)', color: 'var(--text)' }}>
        <div className="bgimg" style={{ background: 'linear-gradient(135deg, var(--grad-from), var(--grad-to))', bottom: 60 }} />
        <div style={{ position: 'absolute', bottom: 44, left: '50%', transform: 'translateX(-50%)', width: 40, height: 40, borderRadius: 20, background: 'var(--primary)', border: '4px solid var(--card)', color: '#fff', display: 'grid', placeItems: 'center', fontSize: 24, zIndex: 2 }}>+</div>
        <div style={{ position: 'absolute', bottom: 10, width: '100%', textAlign: 'center', fontWeight: 600, fontSize: 13, zIndex: 2 }}>{t('report')}</div>
      </Link>
      {items?.slice(0, 12).map((s) => (
        <Link key={s.id} to={`/incidents/${s.id}`} className="story">
          <div className="bgimg" style={s.thumb ? { backgroundImage: `url(${s.thumb})` } : { background: `linear-gradient(160deg, ${BG[s.category]}, #111)` }} />
          <div className="ring">{CATS[s.category]?.icon}</div>
          <div className="cap">{s.title.replace('[DEMO] ', '')}<div style={{ fontWeight: 400, opacity: .85 }}>{timeAgo(s.created_at, lang)}</div></div>
        </Link>
      ))}
    </div>
  )
}
