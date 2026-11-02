import { useEffect, useMemo, useRef, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { api } from '../lib/api'
import { useAuth } from '../lib/auth'
import { BUNDLED, chooseLanguage, type Language, useI18n } from '../lib/i18n'
import { IGlobe } from './Icons'
import { Sk } from './Skeleton'
import { X, Check } from 'lucide-react'

export const useLanguages = () => useQuery({ queryKey: ['languages'], queryFn: () => api<{ data: Language[] }>('/languages'), staleTime: 3_600_000, placeholderData: { data: BUNDLED } })

/**
 * Searchable worldwide language selector. Shows native and English names; RTL languages are marked.
 * The choice is saved on the device and on the profile. English is the default; nothing is auto detected.
 */
export function LanguagePicker({ variant = 'icon' }: { variant?: 'icon' | 'field' }) {
  const { lang, t } = useI18n(); const { user } = useAuth()
  const [open, setOpen] = useState(false)
  const [q, setQ] = useState('')
  const [busy, setBusy] = useState<string | null>(null)
  const list = useLanguages()
  const input = useRef<HTMLInputElement>(null)
  useEffect(() => { if (open) setTimeout(() => input.current?.focus(), 30); else setQ('') }, [open])
  useEffect(() => { const k = (e: KeyboardEvent) => e.key === 'Escape' && setOpen(false); addEventListener('keydown', k); return () => removeEventListener('keydown', k) }, [])
  const langs = list.data?.data ?? BUNDLED
  const cur = langs.find((l) => l.code === lang) ?? BUNDLED[0]
  const shown = useMemo(() => {
    const s = q.trim().toLowerCase()
    return langs.filter((l) => !s || l.code.startsWith(s) || l.native_name.toLowerCase().includes(s) || l.english_name.toLowerCase().includes(s))
  }, [langs, q])
  const pick = async (l: Language) => {
    setBusy(l.code)
    try { await chooseLanguage(l.code) } finally { setBusy(null) }
    if (user) api('/auth/me', { method: 'PATCH', body: { locale: l.code } }).catch(() => {})
    setOpen(false)
  }
  return (
    <>
      <button type="button" data-testid="lang-toggle" className={variant === 'icon' ? 'iconbtn lang-btn' : 'input lang-field'} onClick={() => setOpen(true)} aria-haspopup="dialog" aria-label={t('ui.switch_language')} title={`${cur.native_name} · ${cur.english_name}`}>
        <IGlobe size={18} />{variant === 'icon' ? <span className="lang-code">{cur.code.toUpperCase()}</span> : <span>{cur.native_name} <span className="muted">· {cur.english_name}</span></span>}
      </button>
      {open && (
        <div className="modal-bg" onClick={() => setOpen(false)}>
          <div className="card modal lang-modal fade-in" role="dialog" aria-modal="true" aria-label={t('ui.language')} onClick={(e) => e.stopPropagation()}>
            <div className="modal-head"><b>{t('ui.choose_language')}</b><button className="iconbtn" onClick={() => setOpen(false)} aria-label={t('cancel')}><X size={18} /></button></div>
            <input ref={input} data-testid="lang-search" className="pill-input" placeholder={t('ui.search_languages')} value={q} onChange={(e) => setQ(e.target.value)} />
            <div className="lang-list" role="listbox">
              {list.isFetching && !list.data ? Array.from({ length: 6 }).map((_, i) => <Sk key={i} h={44} r={8} style={{ marginBottom: 6 }} />)
                : shown.length === 0 ? <div className="muted small" style={{ padding: 12 }}>{t('ui.no_results')}</div>
                : shown.map((l) => (
                  <button key={l.code} role="option" aria-selected={l.code === lang} data-lang={l.code} className={'lang-opt' + (l.code === lang ? ' on' : '')} onClick={() => pick(l)} disabled={!!busy}>
                    <span className="lang-native" dir={l.rtl ? 'rtl' : 'ltr'}>{l.native_name}</span>
                    <span className="muted small">{l.english_name}{l.rtl ? ' · RTL' : ''}</span>
                    <span className="grow" />
                    {busy === l.code ? <span className="spinner" /> : !l.hand_translated && <span className="badge" title={t('ui.machine_translated_hint')}>{t('ui.machine_translated')}</span>}
                    {l.code === lang && <Check size={16} aria-hidden />}
                  </button>
                ))}
            </div>
            <div className="small muted" style={{ padding: '8px 4px 0' }}>{t('ui.language_note')}</div>
          </div>
        </div>
      )}
    </>
  )
}
