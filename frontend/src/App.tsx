import i18n from './lib/i18n'
import { lazy, Suspense, useEffect } from 'react'
import { BrowserRouter, Navigate, Outlet, Route, Routes } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { LeftSidebar, RightSidebar, TopNav, useTheme } from './components/Shell'
import { PostSkeleton } from './components/Skeleton'
import { useAuth } from './lib/auth'
import { flushDrafts } from './lib/offline'
import { useToast } from './components/Toast'
import Landing from './pages/Landing'
import Feed from './pages/Feed'

// Route level code splitting: heavy pages (Leaflet map, report flow) load on demand.
const Report = lazy(() => import('./pages/Report'))
const Responder = lazy(() => import('./pages/Responder'))
const MapPage = lazy(() => import('./pages/MapPage'))
const IncidentDetail = lazy(() => import('./pages/IncidentDetail'))
const Moderation = lazy(() => import('./pages/Moderation'))
const Profile = lazy(() => import('./pages/Profile'))
const Settings = lazy(() => import('./pages/Settings'))
const Notifications = lazy(() => import('./pages/Notifications'))
const Admin = lazy(() => import('./pages/Admin'))
const Auth = { Login: lazy(() => import('./pages/Auth').then((m) => ({ default: m.Login }))), Register: lazy(() => import('./pages/Auth').then((m) => ({ default: m.Register }))), Callback: lazy(() => import('./pages/Auth').then((m) => ({ default: m.AuthCallback }))) }

function Layout() {
  const theme = useTheme()
  return (
    <>
      <TopNav theme={theme} />
      <div className="layout">
        <LeftSidebar />
        <Suspense fallback={<div className="main"><PostSkeleton /></div>}><Outlet /></Suspense>
        <RightSidebar />
      </div>
      <footer className="small muted" style={{ textAlign: 'center', padding: 16 }}>© 2026 RescueLens</footer>
    </>
  )
}

/** Full width pages (live map, admin panel): top bar only, no side columns. */
function WideLayout() {
  const theme = useTheme()
  return (<><TopNav theme={theme} /><Suspense fallback={<div style={{ padding: 16 }}><PostSkeleton /></div>}><Outlet /></Suspense></>)
}

function RequireMod({ children }: { children: React.ReactNode }) {
  const { user, loading } = useAuth()
  if (loading) return <div className="main"><PostSkeleton /></div>
  return user && ['moderator', 'admin'].includes(user.role) ? <>{children}</> : <Navigate to="/feed" replace />
}

function OfflineSync() {
  const qc = useQueryClient(); const toast = useToast(); const { user } = useAuth()
  useEffect(() => {
    const go = () => user && flushDrafts().then((n) => { if (n) { toast(i18n.t('ui.offline_posted', { count: n })); qc.invalidateQueries({ queryKey: ['feed'] }) } })
    go(); window.addEventListener('online', go); return () => window.removeEventListener('online', go)
  }, [user])
  return null
}

/** Content (incident titles) is served in the UI language, so a language switch refetches everything once. */
function LangRefetch() {
  const qc = useQueryClient()
  useEffect(() => { const f = () => qc.invalidateQueries(); i18n.on('languageChanged', f); return () => i18n.off('languageChanged', f) }, [qc])
  return null
}

export default function App() {
  return (
    <BrowserRouter>
      <OfflineSync />
      <LangRefetch />
      <Suspense fallback={null}>
        <Routes>
          <Route path="/" element={<Landing />} />
          <Route path="/login" element={<Auth.Login />} />
          <Route path="/register" element={<Auth.Register />} />
          <Route path="/auth/callback" element={<Auth.Callback />} />
          <Route element={<Layout />}>
            <Route path="/feed" element={<Feed />} />
            <Route path="/report" element={<Report />} />
            <Route path="/incidents/:id" element={<IncidentDetail />} />
            <Route path="/profile" element={<Profile />} />
            <Route path="/moderation" element={<RequireMod><Moderation /></RequireMod>} />
            <Route path="/settings" element={<Settings />} />
            <Route path="/notifications" element={<Notifications />} />
            <Route path="/responder" element={<Responder />} />
          </Route>
          <Route element={<WideLayout />}>
            <Route path="/map" element={<MapPage />} />
            <Route path="/admin" element={<RequireMod><Admin /></RequireMod>} />
            <Route path="/admin/:section" element={<RequireMod><Admin /></RequireMod>} />
            <Route path="/admin/:section/:id" element={<RequireMod><Admin /></RequireMod>} />
          </Route>
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </Suspense>
    </BrowserRouter>
  )
}
