import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import { Toaster } from 'react-hot-toast'
import { useAuthStore } from '@/store/auth'
import Layout from '@/components/Layout'
import LoginPage from '@/pages/LoginPage'
import Dashboard from '@/pages/Dashboard'
import LeadsList from '@/pages/LeadsList'
import PropertiesList from '@/pages/PropertiesList'
import PropertyDetail from '@/pages/PropertyDetail'
import AppointmentsPage from '@/pages/AppointmentsPage'
import VisitsPage from '@/pages/VisitsPage'
import ProposalsPage from '@/pages/ProposalsPage'
import MarketingPage from '@/pages/MarketingPage'
import StatsPage from '@/pages/StatsPage'
import SettingsPage from '@/pages/SettingsPage'
import GuidePage from '@/pages/GuidePage'

const queryClient = new QueryClient({
  defaultOptions: {
    queries: { retry: 1, staleTime: 30_000, refetchOnWindowFocus: false },
  },
})

function RequireAuth({ children }: { children: React.ReactNode }) {
  const token = useAuthStore((s) => s.token)
  if (!token) return <Navigate to="/login" replace />
  return <>{children}</>
}

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter basename="/admin">
        <Routes>
          <Route path="/login" element={<LoginPage />} />
          <Route
            element={
              <RequireAuth>
                <Layout />
              </RequireAuth>
            }
          >
            <Route path="/" element={<Dashboard />} />
            <Route path="/leads" element={<LeadsList />} />
            <Route path="/immobili" element={<PropertiesList />} />
            <Route path="/immobili/:id" element={<PropertyDetail />} />
            <Route path="/appuntamenti" element={<AppointmentsPage />} />
            <Route path="/visite" element={<VisitsPage />} />
            <Route path="/proposte" element={<ProposalsPage />} />
            <Route path="/marketing" element={<MarketingPage />} />
            <Route path="/statistiche" element={<StatsPage />} />
            <Route path="/impostazioni" element={<SettingsPage />} />
            <Route path="/guida" element={<GuidePage />} />
          </Route>
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </BrowserRouter>
      <Toaster
        position="top-right"
        toastOptions={{
          style: { borderRadius: 12, border: '1px solid rgba(194,155,82,.4)', color: '#16273F' },
        }}
      />
    </QueryClientProvider>
  )
}
