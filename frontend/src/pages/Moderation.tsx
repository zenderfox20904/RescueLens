import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, errMsg } from '../lib/api'
import { CATS, timeAgo, useI18n } from '../lib/i18n'
import type { Incident } from '../lib/types'
import { StatusBadge } from '../components/PostCard'
import { ListSkeleton } from '../components/Skeleton'
import { useToast } from '../components/Toast'

export function ModActions({ i }: { i: Incident }) {
  const { lang } = useI18n()
  const qc = useQueryClient()
  const toast = useToast()
  const [note, setNote] = useState('')
  const [into, setInto] = useState('')
  const m = useMutation({
    mutationFn: (status: string) => api(`/mod/incidents/${i.id}/status`, { body: { status, note: note || undefined } }),
    onSuccess: () => { qc.invalidateQueries(); setNote(''); toast(lang === 'bn' ? 'অবস্থা হালনাগাদ হয়েছে' : 'Status updated') },
    onError: (e) => toast(errMsg(e)),
  })
  const merge = async () => { try { await api(`/mod/incidents/${i.id}/merge`, { body: { into } }); qc.invalidateQueries(); toast('Merged') } catch (e) { toast(errMsg(e)) } }
  return (
    <div className="card" style={{ padding: 12, display: 'grid', gap: 8 }}>
      <b>🛡️ {lang === 'bn' ? 'মডারেটর প্যানেল' : 'Moderator actions'}</b>
      {i.private && <div className="small muted">{lang === 'bn' ? 'সঠিক অবস্থান (গোপন)' : 'Precise location (private)'}: {i.private.lat}, {i.private.lng}</div>}
      <input className="input" placeholder={lang === 'bn' ? 'নোট (ঐচ্ছিক)' : 'Note (optional)'} value={note} maxLength={500} onChange={(e) => setNote(e.target.value)} />
      <div className="chips">
        {[['verified', '✔ Verify', 'btn-primary'], ['disputed', 'Dispute', ''], ['resolved', 'Resolve', ''], ['expired', 'Expire', ''], ['removed', 'Remove', 'btn-danger']].map(([s, l, c]) => (
          <button key={s} className={'btn ' + c} disabled={m.isPending || i.status === s} onClick={() => m.mutate(s)}>{l}</button>))}
      </div>
      <div className="row"><input className="input" placeholder={lang === 'bn' ? 'যে রিপোর্টে মার্জ হবে তার ID' : 'Merge into report ID'} value={into} onChange={(e) => setInto(e.target.value)} /><button className="btn" disabled={into.length !== 26} onClick={merge}>Merge</button></div>
    </div>
  )
}

export default function Moderation() {
  const { lang } = useI18n()
  const [status, setStatus] = useState('reported')
  const q = useQuery({ queryKey: ['mod', status], queryFn: () => api<{ data: Incident[] }>(`/mod/incidents?status=${status}`), refetchInterval: 10000 })
  return (
    <div className="main">
      <div className="card" style={{ padding: 12 }}>
        <h2 style={{ margin: '0 0 8px' }}>🛡️ {lang === 'bn' ? 'মডারেশন কিউ' : 'Moderation queue'}</h2>
        <div className="chips">{['reported', 'corroborated', 'disputed', 'verified', 'resolved', 'removed'].map((s) => <button key={s} className={'chip' + (status === s ? ' on' : '')} onClick={() => setStatus(s)}>{s}</button>)}</div>
      </div>
      {q.isLoading ? <div className="card" style={{ padding: 12 }}><ListSkeleton rows={6} /></div> : q.data?.data.length === 0 ? <div className="card muted" style={{ padding: 16 }}>Empty</div> : q.data?.data.map((i) => (
        <div key={i.id} className="card" style={{ padding: 12 }}>
          <div className="row"><span style={{ fontSize: 24 }}>{CATS[i.category]?.icon}</span><Link to={`/incidents/${i.id}`} className="grow" style={{ color: 'var(--text)' }}><b>{i.title}</b><div className="small muted">{i.location.area_name} · {timeAgo(i.created_at, lang)} · 👁️ {i.counts.confirm} · ⚑ {i.counts.dispute} · ID {i.id}</div></Link><StatusBadge i={i} /></div>
          <div style={{ marginTop: 8 }}><ModActions i={i} /></div>
        </div>))}
    </div>
  )
}
