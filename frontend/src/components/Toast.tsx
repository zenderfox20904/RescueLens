import { createContext, useContext, useState, useCallback, useRef, type ReactNode } from 'react'
export type ToastAction = { label: string; onClick: () => void }
type Show = (m: string, action?: ToastAction, ms?: number) => void
const T = createContext<Show>(() => {})
/** Toasts with an optional action button (used for Undo after a delete moves an item to Trash). */
export function ToastProvider({ children }: { children: ReactNode }) {
  const [msg, setMsg] = useState<{ m: string; a?: ToastAction } | null>(null)
  const timer = useRef<number | undefined>(undefined)
  const show = useCallback<Show>((m, a, ms) => { clearTimeout(timer.current); setMsg({ m, a }); timer.current = window.setTimeout(() => setMsg(null), ms ?? (a ? 7000 : 4000)) }, [])
  return <T.Provider value={show}>{children}{msg && (
    <div className="toast" role="status" data-testid="toast"><span>{msg.m}</span>{msg.a && <button className="toast-act" onClick={() => { msg.a!.onClick(); setMsg(null) }}>{msg.a.label}</button>}</div>
  )}</T.Provider>
}
export const useToast = () => useContext(T)
