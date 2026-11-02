import { useEffect, useRef, useState, type ReactNode } from 'react'
import { Link, NavLink, useNavigate } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '../lib/api'
import { useAuth } from '../lib/auth'
import { CATS, timeAgo, useI18n } from '../lib/i18n'
import { LanguagePicker } from './LanguagePicker'
import type { Notification } from '../lib/types'
import { LogoMark } from './Logo'
import { EmergencyBanner, useEmergency } from './Emergency'
import { IBell, IHome, IMap, IMoon, IPlus, IShield, ISun, IUser, ILogout, ISettings } from './Icons'
import { ListSkeleton, Sk } from './Skeleton'
import { drafts, flushDrafts } from '../lib/offline'
import { useToast } from './Toast'
import { Ambulance } from 'lucide-react'
import { notifIcon } from '../lib/icons'

export function useTheme() {
  const [theme, setTheme] = useState<'light' | 'dark'>(() => (localStorage.getItem('rl_theme') as any) || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'))
  useEffect(() => { document.documentElement.dataset.theme = theme; localStorage.setItem('rl_theme', theme) }, [theme])
  return { theme, toggle: () => setTheme((t) => (t === 'dark' ? 'light' : 'dark')) }
}

export const initials = (n?: string | null) => (n || '?').trim().replace(/\(.*?\)/g, "").split(/\s+/).filter(Boolean).map((p) => p[0]).slice(0, 2).join('').toUpperCase()
export const Avatar = ({ name, size = 40 }: { name?: string | null; size?: number }) => <div className="avatar" style={{ width: size, height: size, fontSize: size * 0.4 }}>{initials(name)}</div>

function Bell() {
  const { t, lang } = useI18n()
  const [open, setOpen] = useState(false)
  const qc = useQueryClient()
  const nav = useNavigate()
  const ref = useRef<HTMLDivElement>(null)
  const { data, isLoading } = useQuery({ queryKey: ['notifications'], queryFn: () => api<{ unread: number; data: Notification[] }>('/notifications'), refetchInterval: 10000 })
  useEffect(() => { const h = (e: MouseEvent) => { if (!ref.current?.contains(e.target as Node)) setOpen(false) }; document.addEventListener('click', h); return () => document.removeEventListener('click', h) }, [])
  const readAll = async () => { await api('/notifications/read-all', { method: 'POST' }); qc.invalidateQueries({ queryKey: ['notifications'] }) }
  return (
    <div ref={ref} style={{ position: 'relative' }}>
      <button className="iconbtn" aria-label={t('notifications')} onClick={() => setOpen((o) => !o)}><IBell size={20} />{!!data?.unread && <span className="dot">{data.unread > 9 ? '9+' : data.unread}</span>}</button>
      {open && (
        <div className="card dropdown fade-in">
          <div className="row" style={{ padding: '4px 8px' }}><b style={{ fontSize: 22 }} className="grow">{t('notifications')}</b><button className="btn btn-ghost small" onClick={readAll}>{t('markRead')}</button><Link className="btn btn-ghost small" to="/notifications" onClick={() => setOpen(false)}>{t('ui.see_all')}</Link></div>
          {isLoading ? <ListSkeleton rows={4} /> : !data?.data.length ? <p className="muted" style={{ padding: 8 }}>{t('noNotif')}</p> :
            data.data.map((n) => (
              <a key={n.id} className={'notif' + (n.read ? '' : ' unread')} href="#" onClick={(e) => { e.preventDefault(); setOpen(false); if (n.incident_id) nav('/incidents/' + n.incident_id) }}>
                <div className="sideicon" style={{ flex: 'none' }}>{notifIcon(n.type)}</div>
                <div><div className="small">{n.title}</div>{n.body && <div className="small muted">{n.body}</div>}<div className="small" style={{ color: 'var(--primary)' }}>{timeAgo(n.created_at, lang)}</div></div>
              </a>
            ))}
        </div>
      )}
    </div>
  )
}

export function TopNav({ theme }: { theme: ReturnType<typeof useTheme> }) {
  const { user, logout } = useAuth()
  const { t } = useI18n()
  const nav = useNavigate()
  const tab = (to: string, icon: ReactNode, label: string) => <NavLink to={to} className={({ isActive }) => 'navtab' + (isActive ? ' active' : '')} title={label} aria-label={label}>{icon}</NavLink>
  return (
    <header className="topnav">
      <Link to="/feed" className="brand"><LogoMark size={40} /><span className="hide-sm">RescueLens</span></Link>
      <div className="search" style={{ width: 240 }}><input className="pill-input" placeholder={t('ui.search_rescuelens')} onKeyDown={(e) => { if (e.key === 'Enter') nav('/feed?q=' + encodeURIComponent((e.target as HTMLInputElement).value)) }} /></div>
      <nav className="center">
        {tab('/feed', <IHome size={26} />, t('feed'))}
        {tab('/map', <IMap size={26} />, t('map'))}
        {tab('/report', <IPlus size={26} />, t('report'))}
        {user && (user.role === 'moderator' || user.role === 'admin') && tab('/admin', <IShield size={26} />, t('ui.admin_panel'))}
      </nav>
      <LangToggle />
      <button className="iconbtn" onClick={theme.toggle} aria-label={t('ui.theme')}>{theme.theme === 'dark' ? <ISun size={20} /> : <IMoon size={20} />}</button>
      {user ? <>
        <Bell />
        <Link to="/profile" aria-label={t('profile')}><Avatar name={user.name} size={40} /></Link>
        <button className="iconbtn" onClick={async () => { await logout(); nav('/') }} aria-label={t('logout')}><ILogout size={20} /></button>
      </> : <Link to="/login" className="btn btn-primary">{t('login')}</Link>}
    </header>
  )
}

export function LeftSidebar() {
  const { user } = useAuth()
  const { t, lang } = useI18n()
  const [n, setN] = useState(drafts.list().length)
  const toast = useToast()
  useEffect(() => { const h = () => setN(drafts.list().length); window.addEventListener('rl-drafts', h); return () => window.removeEventListener('rl-drafts', h) }, [])
  const item = (to: string, icon: ReactNode, label: string) => <Link to={to} className="sideitem"><span className="sideicon">{icon}</span>{label}</Link>
  return (
    <aside className="sidebar left">
      {user && <Link to="/profile" className="sideitem"><Avatar name={user.name} size={36} />{user.name}</Link>}
      {item('/feed', <IHome size={18} />, t('feed'))}
      {item('/map', <IMap size={18} />, t('map'))}
      {item('/report', <IPlus size={18} />, t('report'))}
      {item('/feed?mine=1', <IUser size={18} />, t('ui.my_reports'))}
      {user && item('/notifications', <IBell size={18} />, t('notifications'))}
      {user && user.account_type && user.account_type !== 'citizen' && item('/responder', <Ambulance size={20} />, t('ui.responder_inbox'))}
      {user && (user.role === 'moderator' || user.role === 'admin') && item('/admin', <IShield size={18} />, t('ui.admin_panel'))}
      {user && item('/settings', <ISettings size={18} />, t('ui.settings'))}
      {n > 0 && <div className="card" style={{ padding: 12, marginTop: 8 }}><b>{t('drafts')}: {n}</b><button className="btn btn-primary" style={{ width: '100%', marginTop: 8 }} onClick={async () => toast(`${await flushDrafts()} sent`)}>{t('sendNow')}</button></div>}
      <div className="divider" />
      <div className="section-title">{t('category')}</div>
      {Object.entries(CATS).map(([k, v]) => <Link key={k} to={'/feed?category=' + k} className="sideitem"><span className="sideicon">{v.icon}</span>{v[lang]}</Link>)}
      <p className="footer-note">© 2026 RescueLens</p>
    </aside>
  )
}

export function RightSidebar() {
  const { t, lang } = useI18n()
  const stats = useQuery({ queryKey: ['stats'], queryFn: () => api<{ data: { active: number; verified: number; last_24h: number } }>('/stats'), refetchInterval: 30000 })
  const res = useEmergency()
  const health = useQuery({ queryKey: ['health'], queryFn: () => api<any>('/health'), staleTime: 60_000 })
  return (
    <aside className="sidebar right">
      <div style={{ marginBottom: 12 }}><EmergencyBanner /></div>
      <div className="section-title">{t('stats')}</div>
      <div className="card" style={{ padding: 12 }}>
        {stats.isLoading ? <ListSkeleton rows={3} avatar={false} /> : stats.data && (
          <div style={{ display: 'grid', gap: 8 }}>
            <div className="row"><span className="grow">{t('active')}</span><b>{stats.data.data.active}</b></div>
            <div className="row"><span className="grow">{t('verified')}</span><b>{stats.data.data.verified}</b></div>
            <div className="row"><span className="grow">{t('last24')}</span><b>{stats.data.data.last_24h}</b></div>
          </div>)}
      </div>
      <div className="section-title">{t('ui.verified_emergency_contacts')}</div>
      <div className="card" style={{ padding: 12 }}>
        {res.isLoading ? <ListSkeleton rows={1} /> : !res.data?.data.length ? <div className="small muted">{t('ui.no_verified_contacts')}</div> : res.data.data.map((r) => (
          <div key={r.id} className="small"><b>{lang === 'bn' ? r.name_bn || r.name : r.name}</b>: <a href={'tel:' + r.phone}>{r.phone}</a><div className="muted">{t('ui.source')}: <a href={r.source_url} target="_blank" rel="noreferrer">{r.source_name}</a></div></div>
        ))}
      </div>
      <div className="section-title">AI</div>
      <div className="card small" style={{ padding: 12 }}>
        {health.isLoading ? <Sk h={30} /> : health.data?.ai?.mode === 'live'
          ? <><span className="badge">Meta AI</span> {t('ui.meta_ai_live')}</>
          : <><span className="badge badge-demo">DEMO</span> {t('ui.live_ai_not_configured_demo')}</>}
      </div>
      <p className="footer-note">{t('neverSafe')}</p>
    </aside>
  )
}

/** Top bar language switcher: the searchable worldwide picker. */
export function LangToggle({ className = 'iconbtn' }: { className?: string }) {
  return <LanguagePicker variant={className === 'iconbtn' ? 'icon' : 'field'} />
}
