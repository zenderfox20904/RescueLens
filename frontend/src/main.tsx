import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import './styles/tokens.css'
import './index.css'
import App from './App'
import { I18nProvider } from './lib/i18n'
import { AuthProvider } from './lib/auth'
import { ToastProvider } from './components/Toast'
import { ConfirmProvider } from './components/Confirm'

// Short stale time plus background refetch keeps the feed live without hammering the API.
const qc = new QueryClient({ defaultOptions: { queries: { staleTime: 5_000, gcTime: 5 * 60_000, retry: 1, refetchOnWindowFocus: true } } })

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <QueryClientProvider client={qc}>
      <I18nProvider><AuthProvider><ToastProvider><ConfirmProvider><App /></ConfirmProvider></ToastProvider></AuthProvider></I18nProvider>
    </QueryClientProvider>
  </StrictMode>,
)
