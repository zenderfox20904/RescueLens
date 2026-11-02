import { useSyncExternalStore } from 'react'
import { api } from './api'

// The api helper appends the current UI language to incident reads.
/** Whole post translation: title, body, the Meta AI analysis and comments, translated together in one call. */
export type PostTr = { lang: string; title: string; description: string | null; analysis: { summary: string | null; reasons: string[]; precautions: string[] } | null; comments: Record<string, string> }
type Entry = { status: 'loading' | 'on' | 'error'; data?: PostTr }

const state = new Map<string, Entry>()
const subs = new Set<() => void>()
const emit = () => subs.forEach((f) => f())
const subscribe = (f: () => void) => { subs.add(f); return () => { subs.delete(f) } }

export function usePostTr(id: string): Entry | undefined {
  return useSyncExternalStore(subscribe, () => state.get(id))
}

export async function togglePostTr(id: string, lang: string) {
  const cur = state.get(id)
  if (cur?.status === 'on' && cur.data?.lang === lang) { state.delete(id); emit(); return }
  if (cur?.status === 'loading') return
  state.set(id, { status: 'loading' }); emit()
  try {
    const r = await api<{ data: PostTr }>(`/incidents/${id}/translation`)
    state.set(id, { status: 'on', data: r.data })
  } catch {
    state.set(id, { status: 'error' })
  }
  emit()
}
