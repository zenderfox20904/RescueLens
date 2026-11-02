import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { api, errMsg } from '../lib/api'
import { fmtDateTime, fmtNumber, useI18n } from '../lib/i18n'
import { ORG_ICON, type DispatchRow, type Org } from '../lib/types'
import { ListSkeleton } from '../components/Skeleton'
import { useToast } from '../components/Toast'

const NEXT: Record<string, string[]> = { alerted: ['accepted', 'declined'], accepted: ['en_route'], en_route: ['on_scene'], on_scene: ['resolved'], resolved: [], declined: [] }

/** Responder inbox: live requests from AI dispatch, with accept, en route, on scene and resolved. */
export default function Responder() {
  const { t, lang } = useI18n()
  const toast = useToast()
  const qc = useQueryClient()
  const q = useQuery({ queryKey: ['org-dispatches'], queryFn: () => api<{ organization: Org | null; data: DispatchRow[] }>('/org/dispatches'), refetchInterval: 5000 })
  const m = useMutation({
    mutationFn: ({ id, status }: { id: number; status: string }) => api(`/org/dispatches/${id}/status`, { body: { status } }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['org-dispatches'] }); toast(t('ui.status_updated')) },
    onError: (e) => toast(errMsg(e)),
  })
  const org = q.data?.organization
  return (
    <div className="main">
      <div className="card" style={{ padding: 16 }}>
        <h2 style={{ margin: 0 }}>{t('ui.responder_inbox')}</h2>
        {q.isLoading ? <ListSkeleton rows={1} /> : !org ? <div className="muted">{t('ui.not_an_org')}</div> : (
          <div className="row" style={{ gap: 8, marginTop: 8 }}>
            <span style={{ fontSize: 22 }}>{ORG_ICON[org.type]}</span><b>{org.name}</b>
            {org.verified ? <span className="vbadge">✓ {t('ui.verified_org')}</span> : <span className="sev-pill sev-pill-moderate">{t('ui.pending_verification')}</span>}
          </div>)}
      </div>
      {q.isLoading ? <ListSkeleton rows={4} /> : !q.data?.data.length ? <div className="card nearby-empty muted" style={{ padding: 16 }}>{t('ui.no_requests_yet')}</div> : q.data.data.map((d) => (
        <article key={d.id} className="card nearby" data-testid="dispatch-card">
          <div className="nearby-head">
            <Link to={`/incidents/${d.incident?.id}`}><b>{d.incident?.title.replace('[DEMO] ', '')}</b></Link>
            <span className={`dstatus dstatus-${d.status}`}>{t(`ui.dispatch_${d.status}`)}</span>
          </div>
          <div className="small muted">{d.incident?.area_name} · {t('ui.km_away', { km: fmtNumber(d.distance_km, lang, 1) })} · {fmtDateTime(d.updated_at, lang)}</div>
          <div className="row" style={{ gap: 8, marginTop: 10, flexWrap: 'wrap' }}>
            {NEXT[d.status].map((s) => <button key={s} className={'btn' + (s === 'declined' ? '' : ' btn-primary')} disabled={m.isPending} onClick={() => m.mutate({ id: d.id, status: s })}>{t(`ui.dispatch_action_${s}`)}</button>)}
            {d.incident && <a className="btn" href={`https://www.google.com/maps/dir/?api=1&destination=${d.incident.lat},${d.incident.lng}`} target="_blank" rel="noopener noreferrer">🧭 {t('ui.directions')}</a>}
          </div>
        </article>))}
    </div>
  )
}
