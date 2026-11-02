import { useQuery } from '@tanstack/react-query'
import { api } from '../lib/api'
import { countryName, useI18n } from '../lib/i18n'
import { IPhone } from './Icons'
import { Phone } from 'lucide-react'

export type Resource = { id: number; country_code: string; name: string; name_bn?: string; phone: string; source_name: string; source_url: string; verified_at: string; stale: boolean }

/** Admin verified emergency contacts for the selected country. Nothing is shown that is not in the verified directory. */
export function useEmergency() {
  const { country } = useI18n()
  return useQuery({ queryKey: ['resources', country], queryFn: () => api<{ data: Resource[] }>('/emergency-resources?country=' + country), staleTime: 3600_000 })
}

export function EmergencyBanner({ compact = false }: { compact?: boolean }) {
  const { t, lang, country } = useI18n()
  const q = useEmergency()
  const primary = q.data?.data[0]
  if (q.isLoading) return null
  if (!primary) return <div className="sos small"><Phone size={14} /> {t('ui.no_verified_contacts')} <span style={{ opacity: .85 }}>({countryName(country, lang)})</span></div>
  return (
    <div className="sos"><IPhone size={compact ? 18 : 22} />
      <div>{t('ui.if_a_life_is_at')} <a href={'tel:' + primary.phone}><b>{primary.phone}</b></a>
        {!compact && <div className="small" style={{ opacity: .9 }}>{lang === 'bn' ? primary.name_bn || primary.name : primary.name} · {t('ui.source')}: <a href={primary.source_url} target="_blank" rel="noreferrer">{primary.source_name}</a></div>}
      </div>
    </div>
  )
}
