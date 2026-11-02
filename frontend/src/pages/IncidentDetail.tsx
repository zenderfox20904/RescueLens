import { useParams, Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { api } from '../lib/api'
import { useI18n } from '../lib/i18n'
import type { Incident } from '../lib/types'
import { PostCard } from '../components/PostCard'
import { DetailSkeleton } from '../components/Skeleton'
import { useAuth } from '../lib/auth'
import { ModActions } from './Moderation'

export default function IncidentDetail() {
  const { id } = useParams()
  const { lang, t } = useI18n()
  const { user } = useAuth()
  const q = useQuery({ queryKey: ['incident', id], queryFn: () => api<{ data: Incident }>(`/incidents/${id}`), refetchInterval: 8000 })
  if (q.isLoading) return <DetailSkeleton />
  if (q.isError || !q.data) return <div className="main"><div className="card" style={{ padding: 16 }}>{lang === 'bn' ? 'রিপোর্ট পাওয়া যায়নি' : 'Report not found'}. <Link to="/feed">{t('feed')}</Link></div></div>
  const i = q.data.data
  return (
    <div className="main">
      <PostCard i={i} detail />
      {user && ['moderator', 'admin'].includes(user.role) && <ModActions i={i} />}
      <div className="card" style={{ padding: 16 }}>
        <b>{lang === 'bn' ? 'অবস্থার ইতিহাস' : 'Status timeline'}</b>
        {i.history?.map((h, k) => (
          <div key={k} className="row" style={{ marginTop: 10, alignItems: 'flex-start' }}>
            <div style={{ width: 10, height: 10, borderRadius: 5, background: 'var(--primary)', marginTop: 5 }} />
            <div><b>{t(h.to as any) || h.to}</b> <span className="small muted">{new Date(h.at).toLocaleString(lang === 'bn' ? 'bn-BD' : 'en-GB', { timeZone: 'Asia/Dhaka' })}{h.by_role ? ` · ${h.by_role}` : ''}</span>{h.note && <div className="small">{h.note}</div>}</div>
          </div>))}
        <div className="small muted" style={{ marginTop: 12 }}>{lang === 'bn' ? 'কমিউনিটি সমর্থিত মানে সরকারি যাচাই নয়। শুধু মডারেটর যাচাই চিহ্ন যাচাইকৃত বোঝায়।' : 'Community corroborated is not an official verification. Only the moderator badge means verified.'}</div>
      </div>
    </div>
  )
}
