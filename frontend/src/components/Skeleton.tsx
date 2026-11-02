import i18n from '../lib/i18n'
// Facebook style shimmer placeholders.
export const Sk = ({ w = '100%', h = 12, r = 6, style }: { w?: number | string; h?: number | string; r?: number; style?: React.CSSProperties }) => (
  <div className="sk" style={{ width: w, height: h, borderRadius: r, ...style }} aria-hidden="true" />
)
export function PostSkeleton() {
  return (
    <div className="card post" aria-busy="true" aria-label={i18n.t('ui.loading')}>
      <div className="post-head"><Sk w={40} h={40} r={20} /><div className="grow"><Sk w="40%" h={12} /><Sk w="25%" h={10} style={{ marginTop: 6 }} /></div></div>
      <Sk w="70%" h={16} style={{ marginTop: 14 }} /><Sk h={10} style={{ marginTop: 8 }} /><Sk w="85%" h={10} style={{ marginTop: 6 }} />
      <Sk h={280} r={0} style={{ margin: '12px -16px 0', width: 'calc(100% + 32px)' }} />
      <div className="row" style={{ padding: '12px 0' }}><Sk h={28} /><Sk h={28} /><Sk h={28} /></div>
    </div>
  )
}
export function ListSkeleton({ rows = 5, avatar = true }: { rows?: number; avatar?: boolean }) {
  return <div>{Array.from({ length: rows }).map((_, i) => (
    <div key={i} className="row" style={{ padding: 8 }}>{avatar && <Sk w={36} h={36} r={18} />}<div className="grow"><Sk w="80%" h={11} /><Sk w="45%" h={9} style={{ marginTop: 6 }} /></div></div>
  ))}</div>
}
export function DetailSkeleton() {
  return <div className="main"><PostSkeleton /><div className="card" style={{ padding: 16 }}><ListSkeleton rows={4} /></div></div>
}
