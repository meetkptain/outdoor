import { NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom'
import { useCallback, useEffect, useMemo, useState } from 'react'
import { apiClient, tenantStore } from '@parapente/shared'
import type {
  AxiosResponse,
  TenantBranding,
  TenantState,
} from '@parapente/shared'
import './App.css'

type BrandingPayload = Record<string, unknown>

type BrandingResponse =
  | {
      success?: boolean
      data?: BrandingPayload
      message?: string
    }
  | BrandingPayload

type FetchState = 'idle' | 'loading' | 'ready' | 'error'

export type AdminOutletContext = {
  branding: BrandingPayload | null
  fetchState: FetchState
  tenantState: TenantState
  error: string | null
  refreshTenantState: () => void
}

function App() {
  const [fetchState, setFetchState] = useState<FetchState>('idle')
  const [branding, setBranding] = useState<BrandingPayload | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [tenantState, setTenantState] = useState<TenantState>(() => {
    tenantStore.hydrateFromStorage()
    const current = tenantStore.getState()
    return {
      ...current,
      featureFlags: { ...current.featureFlags },
    }
  })
  const navigate = useNavigate()
  const location = useLocation()

  const syncTenantState = useCallback(() => {
    const current = tenantStore.getState()
    setTenantState({
      ...current,
      featureFlags: { ...current.featureFlags },
    })
  }, [])

  useEffect(() => {
    tenantStore.hydrateFromStorage()
    const storedOrganization = tenantStore.getState().organizationId
    const defaultOrganization = import.meta.env.VITE_DEFAULT_ORGANIZATION_ID
    if (defaultOrganization && storedOrganization !== defaultOrganization) {
      tenantStore.setOrganization(defaultOrganization)
      syncTenantState()
    }

    if (!tenantStore.getState().token) {
      setBranding(null)
      setFetchState('idle')
      return
    }

    setFetchState('loading')
    apiClient
      .get<BrandingResponse>('/branding')
      .then((response: AxiosResponse<BrandingResponse>) => {
        const payload = (
          'data' in response.data && response.data.data
            ? response.data.data
            : response.data
        ) as BrandingPayload | undefined
        if (payload && typeof payload === 'object') {
          setBranding(payload)
          tenantStore.setBranding(payload as TenantBranding)
          syncTenantState()
        }
        setFetchState('ready')
      })
      .catch((err: unknown) => {
        const message =
          err instanceof Error
            ? err.message
            : 'Impossible de récupérer le branding'
        setError(message)
        setFetchState('error')
      })
  }, [syncTenantState, tenantState.token])

  const outletContext = useMemo<AdminOutletContext>(
    () => ({
      branding,
      fetchState,
      tenantState,
      error,
      refreshTenantState: syncTenantState,
    }),
    [branding, fetchState, tenantState, error, syncTenantState],
  )

  const primaryColor = (branding?.primaryColor as string | undefined) ?? '#2563eb'
  const secondaryColor =
    (branding?.secondaryColor as string | undefined) ?? '#0ea5e9'

  const handleLogout = () => {
    tenantStore.reset()
    syncTenantState()
    setBranding(null)
    setFetchState('idle')
    navigate('/login')
  }

  const isLoginRoute = location.pathname === '/login'

  if (isLoginRoute) {
    return (
      <div className="auth-wrapper">
        <main className="auth-main">
          <header className="auth-header">
            <h1>Parapente SaaS Admin</h1>
            <p className="auth-subtitle">
              Connectez-vous pour gérer vos activités multi-niche.
            </p>
          </header>
          <Outlet context={outletContext} />
        </main>
      </div>
    )
  }

  return (
    <div className="app-shell">
      <aside className="sidebar" style={{ borderColor: `${primaryColor}20` }}>
        <div className="sidebar-header">
          {branding?.logoUrl ? (
            <img
              src={String(branding.logoUrl)}
              alt="Brand logo"
              className="brand-logo"
            />
          ) : (
            <div
              className="brand-fallback"
              style={{ background: primaryColor }}
            >
              {tenantState.organizationId?.slice(0, 2)?.toUpperCase() ?? 'SA'}
            </div>
          )}
          <div>
            <p className="sidebar-title">Admin Portal</p>
            <p className="sidebar-subtitle">
              Tenant {tenantState.organizationId ?? 'non défini'}
            </p>
          </div>
        </div>

        <nav className="sidebar-nav">
          <NavLink
            end
            to="/"
            className={({ isActive }) => `nav-link${isActive ? ' active' : ''}`}
          >
            Tableau de bord
          </NavLink>
          <NavLink
            to="/reservations"
            className={({ isActive }) => `nav-link${isActive ? ' active' : ''}`}
          >
            Réservations
          </NavLink>
          <NavLink
            to="/resources"
            className={({ isActive }) => `nav-link${isActive ? ' active' : ''}`}
          >
            Ressources
          </NavLink>
        </nav>

        <div className="sidebar-footer">
          <div
            className="brand-swatch"
            style={{ background: primaryColor }}
          />
          <div
            className="brand-swatch"
            style={{ background: secondaryColor }}
          />
        </div>
      </aside>

      <div className="main-column">
        <header className="app-header">
          <div>
            <h1>Admin Portal</h1>
            <p className="subtitle">
              SaaS multi-niche ·{' '}
              {tenantState.organizationId
                ? `Tenant ${tenantState.organizationId}`
                : 'Tenant non défini'}
            </p>
          </div>
          <div className="user-actions">
            {tenantState.user ? (
              <div className="user-chip">
                <span className="user-initial">
                  {tenantState.user.name.slice(0, 1).toUpperCase()}
                </span>
                <div>
                  <p className="user-name">{tenantState.user.name}</p>
                  <p className="user-role">{tenantState.user.role}</p>
                </div>
              </div>
            ) : (
              <span className="user-placeholder">Non authentifié</span>
            )}

            {tenantState.token ? (
              <button type="button" className="nav-cta nav-cta-secondary" onClick={handleLogout}>
                Se déconnecter
              </button>
            ) : (
              <NavLink to="/login" className="nav-cta">
                Se connecter
              </NavLink>
            )}
          </div>
        </header>

        <main>
          {fetchState === 'loading' && tenantState.token && (
            <section className="panel">
              <p>Chargement du branding…</p>
            </section>
          )}

          {fetchState === 'error' && tenantState.token && (
            <section className="panel panel-error">
              <h2>Erreur</h2>
              <p>{error}</p>
              <p>
                Vérifie la configuration API (variable
                VITE_DEFAULT_ORGANIZATION_ID) et l&apos;endpoint `/branding`.
              </p>
            </section>
          )}

          {!tenantState.token && !isLoginRoute && (
            <section className="panel panel-warning">
              <h2>Authentification requise</h2>
              <p>
                Connecte-toi pour accéder au tableau de bord multi-niche du tenant.
              </p>
              <NavLink to="/login" className="nav-cta">
                Accéder à la page de connexion
              </NavLink>
            </section>
          )}

          <Outlet context={outletContext} />
        </main>
      </div>
    </div>
  )
}

export default App
