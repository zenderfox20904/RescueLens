import { createContext, useContext, useState, useCallback, type ReactNode } from 'react'
const T = createContext<(m: string) => void>(() => {})
export function ToastProvider({ children }: { children: ReactNode }) {
  const [msg, setMsg] = useState<string | null>(null)
  const show = useCallback((m: string) => { setMsg(m); setTimeout(() => setMsg(null), 4000) }, [])
  return <T.Provider value={show}>{children}{msg && <div className="toast" role="status">{msg}</div>}</T.Provider>
}
export const useToast = () => useContext(T)
