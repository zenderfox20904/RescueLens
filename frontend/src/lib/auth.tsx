import { createContext, useContext, useEffect, useState, type ReactNode } from 'react'
import { api, tokenStore } from './api'
import type { User } from './types'

type Ctx = { user: User | null; loading: boolean; setSession: (u: User, token: string) => void; logout: () => Promise<void>; refresh: () => Promise<void> }
const AuthCtx = createContext<Ctx>(null!)
export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [loading, setLoading] = useState(!!tokenStore.get())
  const refresh = async () => {
    if (!tokenStore.get()) { setUser(null); setLoading(false); return }
    try { setUser((await api<{ user: User }>('/auth/me')).user) } catch { setUser(null) } finally { setLoading(false) }
  }
  useEffect(() => { refresh() }, [])
  const setSession = (u: User, token: string) => { tokenStore.set(token); setUser(u) }
  const logout = async () => { try { await api('/auth/logout', { method: 'POST' }) } catch { /* ignore */ } tokenStore.set(null); setUser(null) }
  return <AuthCtx.Provider value={{ user, loading, setSession, logout, refresh }}>{children}</AuthCtx.Provider>
}
export const useAuth = () => useContext(AuthCtx)
