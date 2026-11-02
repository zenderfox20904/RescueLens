import { X } from 'lucide-react'
import { createContext, useContext, useState, useCallback, useRef, useEffect, type ReactNode } from 'react'
export type ToastAction = { label: string; onClick: () => void }
export type ToastRich = { avatar?: string | null; name?: string; severity?: string; time?: string; onOpen?: () => void; kind?: 'info' | 'error' | 'success' }
type Show = (m: string, action?: ToastAction, ms?: number, rich?: ToastRich) => void
type Item = { id: number; m: string; a?: ToastAction; ms: number; r?: ToastRich; leaving?: boolean }
const T = createContext<Show>(() => {})
const MAX = 3
const SEV: Record<string, string> = { critical: '#E41E3F', high: '#F35369', moderate: '#F7B928', low: '#45BD62' }

/** Stacked toasts: auto dismiss (4s, 6s with an action), pause on hover, swipe or close to dismiss, max 3, no duplicates. */
export function ToastProvider({ children }: { children: ReactNode }) {
  const [items, setItems] = useState<Item[]>([])
  const seq = useRef(0)
  const dismiss = useCallback((id: number) => {
    setItems((l) => l.map((x) => (x.id === id ? { ...x, leaving: true } : x)))
    window.setTimeout(() => setItems((l) => l.filter((x) => x.id !== id)), 220)
  }, [])
  const show = useCallback<Show>((m, a, ms, r) => {
    setItems((l) => {
      const rest = l.filter((x) => x.m !== m)
      const next = [...rest, { id: ++seq.current, m, a, r, ms: ms ?? (a ? 6000 : 4000) }]
      return next.slice(-MAX)
    })
  }, [])
  return <T.Provider value={show}>{children}
    <div className="toast-stack" aria-live="polite">{items.map((it) => <ToastView key={it.id} it={it} onClose={() => dismiss(it.id)} />)}</div>
  </T.Provider>
}

function ToastView({ it, onClose }: { it: Item; onClose: () => void }) {
  const [hover, setHover] = useState(false)
  const left = useRef(it.ms), start = useRef(Date.now())
  const [dx, setDx] = useState(0); const sx = useRef<number | null>(null)
  useEffect(() => {
    if (hover || it.leaving) return
    start.current = Date.now()
    const t = window.setTimeout(onClose, left.current)
    return () => { clearTimeout(t); left.current -= Date.now() - start.current }
  }, [hover, it.leaving])
  const r = it.r
  return (
    <div className={'toast' + (it.leaving ? ' leaving' : '') + (r?.kind === 'error' ? ' error' : '')} role="status" data-testid="toast"
      style={{ transform: dx ? `translateX(${dx}px)` : undefined, opacity: dx ? Math.max(0, 1 - Math.abs(dx) / 220) : undefined }}
      onMouseEnter={() => setHover(true)} onMouseLeave={() => setHover(false)}
      onPointerDown={(e) => { sx.current = e.clientX }} onPointerMove={(e) => { if (sx.current !== null && e.buttons) setDx(e.clientX - sx.current) }}
      onPointerUp={() => { if (Math.abs(dx) > 90) onClose(); else setDx(0); sx.current = null }}
      onClick={(e) => { if (r?.onOpen && !(e.target as HTMLElement).closest('button') && Math.abs(dx) < 5) { r.onOpen(); onClose() } }}>
      {r?.name !== undefined && (r.avatar ? <img className="toast-av" src={r.avatar} alt="" style={{ boxShadow: r.severity ? `0 0 0 2px ${SEV[r.severity]}` : undefined }} /> : <span className="toast-av" style={{ boxShadow: r.severity ? `0 0 0 2px ${SEV[r.severity]}` : undefined }}>{(r.name || '?').slice(0, 1).toUpperCase()}</span>)}
      <div className="grow"><div>{it.m}</div>{r?.time && <div className="toast-time">{r.time}</div>}</div>
      {it.a && <button className="toast-act" onClick={() => { it.a!.onClick(); onClose() }}>{it.a.label}</button>}
      <button className="toast-x" aria-label="Close" onClick={onClose}><X size={16} /></button>
    </div>
  )
}
export const useToast = () => useContext(T)
