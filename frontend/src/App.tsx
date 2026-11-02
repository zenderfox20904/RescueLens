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
const MapPage = lazy(() => import('./pages/MapPage'))
const IncidentDetail = lazy(() => import('./pages/IncidentDetail'))
const Moderation = lazy(() => import('./pages/Moderation'))
const Profile = lazy(() => import('./pages/Profile'))
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

function RequireMod({ children }: { children: React.ReactNode }) {
  const { user, loading } = useAuth()
  if (loading) return <div className="main"><PostSkeleton /></div>
  return user && ['moderator', 'admin'].includes(user.role) ? <>{children}</> : <Navigate to="/feed" replace />
}

function OfflineSync() {
  const qc = useQueryClient(); const toast = useToast(); const { user } = useAuth()
  useEffect(() => {
    const go = () => user && flushDrafts().then((n) => { if (n) { toast(`${n} offline report(s) posted`); qc.invalidateQueries({ queryKey: ['feed'] }) } })
    go(); window.addEventListener('online', go); return () => window.removeEventListener('online', go)
  }, [user])
  return null
}

export default function App() {
  return (
    <BrowserRouter>
      <OfflineSync />
      <Suspense fallback={null}>
        <Routes>
          <Route path="/" element={<Landing />} />
          <Route path="/login" element={<Auth.Login />} />
          <Route path="/register" element={<Auth.Register />} />
          <Route path="/auth/callback" element={<Auth.Callback />} />
          <Route element={<Layout />}>
            <Route path="/feed" element={<Feed />} />
            <Route path="/report" element={<Report />} />
            <Route path="/map" element={<MapPage />} />
            <Route path="/incidents/:id" element={<IncidentDetail />} />
            <Route path="/profile" element={<Profile />} />
            <Route path="/moderation" element={<RequireMod><Moderation /></RequireMod>} />
          </Route>
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </Suspense>
    </BrowserRouter>
  )
}
