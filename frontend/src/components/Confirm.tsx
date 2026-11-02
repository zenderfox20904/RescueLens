import { createContext, useCallback, useContext, useEffect, useRef, useState, type ReactNode } from 'react'
import { useI18n } from '../lib/i18n'

type Opts = { title: string; body?: string; confirm?: string; danger?: boolean; icon?: string }
type Ask = (o: Opts) => Promise<boolean>
const C = createContext<Ask>(async () => false)

/** Polished confirmation dialog used before every delete or important action. Promise based: `if (await confirm({...}))`. */
export function ConfirmProvider({ children }: { children: ReactNode }) {
  const { t } = useI18n()
  const [o, setO] = useState<Opts | null>(null)
  const res = useRef<(v: boolean) => void>(() => {})
  const ok = useRef<HTMLButtonElement>(null)
  const ask = useCallback<Ask>((x) => new Promise((r) => { res.current = r; setO(x) }), [])
  const close = (v: boolean) => { res.current(v); setO(null) }
  useEffect(() => {
    if (!o) return
    ok.current?.focus()
    const k = (e: KeyboardEvent) => { if (e.key === 'Escape') close(false) }
    window.addEventListener('keydown', k)
    return () => window.removeEventListener('keydown', k)
  }, [o])
  return <C.Provider value={ask}>{children}{o && (
    <div className="modal-bg confirm-bg" onClick={() => close(false)} data-testid="confirm-dialog">
      <div className="card confirm fade-in" role="alertdialog" aria-modal="true" aria-labelledby="cf-title" onClick={(e) => e.stopPropagation()}>
        <div className={'confirm-icon' + (o.danger === false ? '' : ' danger')}>{o.icon ?? (o.danger === false ? 'ℹ️' : '🗑️')}</div>
        <h3 id="cf-title">{o.title}</h3>
        {o.body && <p className="muted">{o.body}</p>}
        <div className="confirm-actions">
          <button className="btn" onClick={() => close(false)}>{t('ui.cancel')}</button>
          <button ref={ok} className={'btn ' + (o.danger === false ? 'btn-primary' : 'btn-danger')} onClick={() => close(true)}>{o.confirm ?? t('ui.delete')}</button>
        </div>
      </div>
    </div>)}</C.Provider>
}
export const useConfirm = () => useContext(C)
