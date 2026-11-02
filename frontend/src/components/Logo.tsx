export function LogoMark({ size = 40 }: { size?: number }) {
  return <img src="/logo.svg" width={size} height={size} alt="RescueLens" style={{ borderRadius: size * 0.23 }} />
}
export function Wordmark({ size = 40 }: { size?: number }) {
  return <span className="row" style={{ gap: 8 }}><LogoMark size={size} /><span style={{ fontWeight: 800, fontSize: size * 0.6, color: 'var(--primary)', letterSpacing: -0.8 }}>RescueLens</span></span>
}
