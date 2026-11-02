import { ORG_ICON, PIN } from '../lib/icons'
import { Phone, Navigation, BadgeCheck } from 'lucide-react'
import { useQuery } from '@tanstack/react-query'
import { api } from '../lib/api'
import { fmtNumber, useI18n } from '../lib/i18n'
import { type Org } from '../lib/types'
import { ListSkeleton } from './Skeleton'

/** Verified responder organizations near a point. Humans tap to call; nothing is dialed automatically. */
export function NearbyHelp({ lat, lng, radiusKm = 10, types, compact }: { lat: number; lng: number; radiusKm?: number; types?: string[]; compact?: boolean }) {
  const { t, lang } = useI18n()
  const q = useQuery({
    queryKey: ['nearby-help', lat.toFixed(3), lng.toFixed(3), radiusKm, types?.join(',')],
    queryFn: () => api<{ data: Org[] }>(`/nearby-help?lat=${lat}&lng=${lng}&radius_km=${radiusKm}${types?.length ? `&types=${types.join(',')}` : ''}`),
    refetchInterval: 60000,
  })
  const rows = (q.data?.data ?? []).slice(0, compact ? 4 : 12)
  return (
    <section className="card nearby" aria-label={t('ui.nearby_help')}>
      <div className="nearby-head"><b>{t('ui.nearby_help')}</b><span className="small muted">{t('ui.within_km', { km: fmtNumber(radiusKm, lang) })}</span></div>
      {q.isLoading ? <ListSkeleton rows={3} /> : rows.length === 0 ? <div className="small muted nearby-empty">{t('ui.no_verified_help_nearby')}</div> : (
        <ul className="nearby-list">
          {rows.map((o) => (
            <li key={o.id} className="nearby-item">
              <span className="nearby-ic" aria-hidden>{ORG_ICON[o.type] ?? PIN}</span>
              <div className="nearby-body">
                <div className="nearby-name">{o.name}{o.verified && <span className="vbadge" title={t('ui.verified_org')}><BadgeCheck size={14} /></span>}</div>
                <div className="small muted">{t(`ui.org_${o.type}`)} · {t('ui.km_away', { km: fmtNumber(o.distance_km ?? 0, lang, 1) })}{o.service_hours ? ` · ${o.service_hours}` : ''}</div>
              </div>
              <a className="mini" href={`tel:${o.phone.replace(/[^0-9+]/g, '')}`} aria-label={t('ui.call')}><Phone size={16} /> {t('ui.call')}</a>
              <a className="mini" href={`https://www.google.com/maps/dir/?api=1&destination=${o.lat},${o.lng}`} target="_blank" rel="noopener noreferrer"><Navigation size={16} /> {t('ui.directions')}</a>
            </li>))}
        </ul>)}
      <div className="small muted nearby-note">{t('ui.nearby_help_note')}</div>
    </section>
  )
}
