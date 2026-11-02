import { ORG_ICON, PIN } from '../lib/icons'
import { Phone, BadgeCheck } from 'lucide-react'
import { useQuery } from '@tanstack/react-query'
import { api } from '../lib/api'
import { fmtNumber, useI18n } from '../lib/i18n'
import { type DispatchRow } from '../lib/types'
import { ListSkeleton } from './Skeleton'

/** Which verified responders were alerted for this incident, and their live response status. */
export function DispatchPanel({ incidentId }: { incidentId: string }) {
  const { t, lang } = useI18n()
  const q = useQuery({ queryKey: ['dispatches', incidentId], queryFn: () => api<{ data: DispatchRow[]; responder_types: string[] }>(`/incidents/${incidentId}/dispatches`), refetchInterval: 8000 })
  return (
    <section className="card nearby" aria-label={t('ui.responders_alerted')}>
      <div className="nearby-head"><b>{t('ui.responders_alerted')}</b>
        <span className="chips">{q.data?.responder_types.map((x) => <span key={x} className="chip-sm">{ORG_ICON[x]} {t(`ui.org_${x}`)}</span>)}</span></div>
      {q.isLoading ? <ListSkeleton rows={2} /> : !q.data?.data.length ? <div className="small muted nearby-empty">{t('ui.no_responders_alerted')}</div> : (
        <ul className="nearby-list">
          {q.data.data.map((d) => (
            <li key={d.id} className="nearby-item">
              <span className="nearby-ic" aria-hidden>{ORG_ICON[d.organization?.type ?? ''] ?? PIN}</span>
              <div className="nearby-body">
                <div className="nearby-name">{d.organization?.name}{d.organization?.verified && <span className="vbadge"><BadgeCheck size={14} /></span>}</div>
                <div className="small muted">{t(`ui.org_${d.organization?.type}`)} · {t('ui.km_away', { km: fmtNumber(d.distance_km, lang, 1) })}</div>
              </div>
              <span className={`dstatus dstatus-${d.status}`}>{t(`ui.dispatch_${d.status}`)}</span>
              {d.organization && <a className="mini" href={`tel:${d.organization.phone.replace(/[^0-9+]/g, '')}`}><Phone size={16} /> {t('ui.call')}</a>}
            </li>))}
        </ul>)}
      <div className="small muted nearby-note">{t('ui.dispatch_note')}</div>
    </section>
  )
}
