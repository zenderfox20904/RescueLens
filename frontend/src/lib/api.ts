import i18n from './i18n'
export const API = (import.meta.env.VITE_API_URL as string) || 'http://127.0.0.1:8000/api/v1'
export const SHARE_BASE = (import.meta.env.VITE_SHARE_BASE as string) || API.replace(/\/api\/v1\/?$/, '')

export class ApiError extends Error {
  status: number; data: any
  constructor(status: number, data: any) { super(data?.message || `HTTP ${status}`); this.status = status; this.data = data }
}

export const tokenStore = {
  get: () => localStorage.getItem('rl_token'),
  set: (t: string | null) => (t ? localStorage.setItem('rl_token', t) : localStorage.removeItem('rl_token')),
}

export async function api<T = any>(path: string, opts: { method?: string; body?: any; headers?: Record<string, string> } = {}): Promise<T> {
  const headers: Record<string, string> = { Accept: 'application/json', ...(opts.headers || {}) }
  const t = tokenStore.get()
  if (t) headers.Authorization = `Bearer ${t}`
  let body: BodyInit | undefined
  if (opts.body instanceof FormData) body = opts.body
  else if (opts.body !== undefined) { headers['Content-Type'] = 'application/json'; body = JSON.stringify(opts.body) }
  const res = await fetch(API + path, { method: opts.method || (body ? 'POST' : 'GET'), headers, body })
  if (res.status === 204) return undefined as T
  const data = await res.json().catch(() => ({}))
  if (!res.ok) {
    if (res.status === 401 && t) { tokenStore.set(null) }
    throw new ApiError(res.status, data)
  }
  return data as T
}

export const errMsg = (e: unknown) => {
  if (e instanceof ApiError) {
    const errs = e.data?.errors
    if (errs) return Object.values(errs).flat().join(' ')
    return e.message
  }
  return i18n.t(navigator.onLine ? 'ui.something_wrong' : 'ui.offline')
}
