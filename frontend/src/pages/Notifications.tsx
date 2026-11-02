import { Link } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '../lib/api'
import { timeAgo, useI18n } from '../lib/i18n'
import type { Notification } from '../lib/types'
import { ListSkeleton } from '../components/Skeleton'
import { notifIcon } from '../lib/icons'

export default function Notifications() {
  const { t, lang } = useI18n()
  const qc = useQueryClient()
  const { data, isLoading } = useQuery({ queryKey: ['notifications'], queryFn: () => api<{ unread: number; data: Notification[] }>('/notifications'), refetchInterval: 10000 })
  const readAll = async () => { await api('/notifications/read-all', { method: 'POST' }); qc.invalidateQueries({ queryKey: ['notifications'] }) }
  return (
    <div className="main">
      <div className="card" style={{ padding: 8 }}>
        <div className="row" style={{ padding: '4px 8px' }}><h1 className="grow" style={{ margin: 0, fontSize: 24 }}>{t('notifications')}</h1><button className="btn btn-ghost small" onClick={readAll}>{t('markRead')}</button></div>
        {isLoading ? <ListSkeleton rows={6} /> : !data?.data.length ? <p className="muted" style={{ padding: 8 }}>{t('noNotif')}</p> : data.data.map((n) => (
          <Link key={n.id} className={'notif' + (n.read ? '' : ' unread')} to={n.incident_id ? '/incidents/' + n.incident_id : '#'}>
            <div className="sideicon" style={{ flex: 'none', width: 56, height: 56, fontSize: 24 }}>{notifIcon(n.type)}</div>
            <div className="grow"><div>{n.title}</div>{n.body && <div className="small muted">{n.body}</div>}<div className="small" style={{ color: 'var(--primary)', fontWeight: 600 }}>{timeAgo(n.created_at, lang)}</div></div>
            {!n.read && <span style={{ width: 12, height: 12, borderRadius: 6, background: 'var(--primary)', flex: 'none' }} aria-label="unread" />}
          </Link>))}
      </div>
    </div>
  )
}
