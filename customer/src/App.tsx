import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import { Toaster } from 'sonner'
import { auth } from '@/api/client'
import Layout from '@/components/Layout'
import { PropertySelectionProvider } from '@/hooks/useSelectedProperty'
import AccessPage from '@/pages/AccessPage'
import LoginPage from '@/pages/LoginPage'
import HomePage from '@/pages/HomePage'
import VisitsPage from '@/pages/VisitsPage'
import ProposalsPage from '@/pages/ProposalsPage'
import MarketingPage from '@/pages/MarketingPage'
import PracticeStepsPage from '@/pages/PracticeStepsPage'
import AssistantPage from '@/pages/AssistantPage'
import ProfilePage from '@/pages/ProfilePage'
import GuidePage from '@/pages/GuidePage'

const queryClient = new QueryClient({
  defaultOptions: {
    queries: { retry: 1, staleTime: 60_000, refetchOnWindowFocus: false },
  },
})

function RequireAuth({ children }: { children: React.ReactNode }) {
  if (!auth.isLoggedIn()) return <Navigate to="/login" replace />
  return <>{children}</>
}

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter basename="/app">
        <Routes>
          <Route path="/login" element={<LoginPage />} />
          <Route path="/access" element={<AccessPage />} />
          <Route
            element={
              <RequireAuth>
                <PropertySelectionProvider>
                  <Layout />
                </PropertySelectionProvider>
              </RequireAuth>
            }
          >
            <Route path="/" element={<HomePage />} />
            <Route path="/visite" element={<VisitsPage />} />
            <Route path="/proposte" element={<ProposalsPage />} />
            <Route path="/promozione" element={<MarketingPage />} />
            <Route path="/pratiche" element={<PracticeStepsPage />} />
            <Route path="/assistente" element={<AssistantPage />} />
            <Route path="/profilo" element={<ProfilePage />} />
            <Route path="/guida" element={<GuidePage />} />
          </Route>
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </BrowserRouter>
      <Toaster position="top-center" richColors />
    </QueryClientProvider>
  )
}
