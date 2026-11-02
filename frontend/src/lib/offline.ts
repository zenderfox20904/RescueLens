// Offline draft queue: reports made without connectivity are stored locally (text + location only,
// photos are kept as data URLs on this device only) and sent when the browser is back online.
import { api } from './api'
export interface Draft { key: string; created: number; payload: Record<string, any>; image?: string }
const K = 'rl_drafts'
export const drafts = {
  list: (): Draft[] => JSON.parse(localStorage.getItem(K) || '[]'),
  save(d: Draft) { const l = drafts.list().filter((x) => x.key !== d.key); l.push(d); localStorage.setItem(K, JSON.stringify(l)); window.dispatchEvent(new Event('rl-drafts')) },
  remove(key: string) { localStorage.setItem(K, JSON.stringify(drafts.list().filter((x) => x.key !== key))); window.dispatchEvent(new Event('rl-drafts')) },
}
export async function flushDrafts(): Promise<number> {
  let sent = 0
  for (const d of drafts.list()) {
    try {
      const media_ids: string[] = []
      if (d.image) {
        const blob = await (await fetch(d.image)).blob()
        const fd = new FormData(); fd.append('image', blob, 'photo.jpg')
        media_ids.push((await api<{ id: string }>('/media', { body: fd })).id)
      }
      await api('/incidents', { body: { ...d.payload, media_ids }, headers: { 'Idempotency-Key': d.key } })
      drafts.remove(d.key); sent++
    } catch (e: any) { if (!navigator.onLine) break; if (e?.status === 422) drafts.remove(d.key) }
  }
  return sent
}
export const newKey = () => 'rl_' + crypto.randomUUID().replace(/-/g, '')
