import { useEffect, useMemo, useState } from 'react'
import { Outlet } from 'react-router-dom'
import AppNavigation from './components/AppNavigation'
import {
  apiClient,
  tenantStore,
  buildPalette,
  type TenantBranding,
  type AxiosResponse,
} from '@parapente/shared'
import type { ThemePalette } from '@parapente/shared'
import { DEFAULT_ORGANIZATION_ID } from './config/env'
import './App.css'

type BrandingResponse =
  | {
      success?: boolean
      data?: TenantBranding
    }
  | TenantBranding

export type ParaglidingOutletContext = {
  branding: TenantBranding | null
  palette: ThemePalette
  status: 'idle' | 'loading' | 'ready' | 'error'
}

function App() {
  const [branding, setBranding] = useState<TenantBranding | null>(
    tenantStore.getState().branding ?? null,
  )
  const [status, setStatus] = useState<'idle' | 'loading' | 'ready' | 'error'>(
    'idle',
  )
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    tenantStore.hydrateFromStorage()
    if (DEFAULT_ORGANIZATION_ID) {
      tenantStore.setOrganization(DEFAULT_ORGANIZATION_ID)
    }

    const unsubscribe = tenantStore.subscribe(() => {
      setBranding(tenantStore.getState().branding ?? null)
    })

    return unsubscribe
  }, [])

  useEffect(() => {
    let mounted = true

    async function fetchBranding() {
      setStatus('loading')
      setError(null)
      try {
        const response: AxiosResponse<BrandingResponse> = await apiClient.get(
          '/branding',
        )
        const payload =
          'data' in response.data && response.data.data
            ? response.data.data
            : response.data

        if (!mounted) return

        const nextBranding =
          payload && typeof payload === 'object'
            ? (payload as TenantBranding)
            : null

        if (nextBranding) {
          tenantStore.setBranding(nextBranding)
          setBranding(nextBranding)
          setStatus('ready')
        } else {
          setStatus('error')
          setError('Branding non disponible')
        }
      } catch (err) {
        if (!mounted) return
        setStatus('error')
        setError(
          err instanceof Error
            ? err.message
            : 'Impossible de récupérer le branding',
        )
      }
    }

    fetchBranding()

    return () => {
      mounted = false
    }
  }, [])

  const palette = useMemo(
    () => buildPalette(branding ?? tenantStore.getState().branding),
    [branding],
  )

  const outletContext: ParaglidingOutletContext = useMemo(
    () => ({
      branding,
      palette,
      status,
    }),
    [branding, palette, status],
  )

  return (
    <div
      className="paragliding-app"
      style={{
        background: `radial-gradient(circle at top, ${palette.primary}33, transparent 55%), linear-gradient(180deg, #1e293b, #020617 60%)`,
        color: palette.primaryForeground,
      }}
    >
      <header className="paragliding-header">
        <div>
          <p className="paragliding-tagline">Expériences outdoor SaaS</p>
          <h1>SkyLift Parapente</h1>
          <p className="paragliding-subtitle">
            Prépare ton vol en parapente en toute confiance&nbsp;: briefing,
            navette, pilote dédié et suivi météo par notre équipe.
          </p>
        </div>
        <div className="paragliding-header-palette">
          <span
            className="paragliding-palette-chip"
            style={{ background: palette.primary }}
          />
          <span
            className="paragliding-palette-chip"
            style={{ background: palette.secondary }}
          />
        </div>
      </header>

      <AppNavigation />

      <main className="paragliding-main">
        {status === 'error' ? (
          <div className="paragliding-error">
            <h2>Oups, impossible de charger le branding</h2>
            <p>{error}</p>
          </div>
        ) : (
          <Outlet context={outletContext} />
        )}
      </main>

      <footer className="paragliding-footer">
        <p>
          Powered by Parapente SaaS · Plateforme multi-activités ·{' '}
          {new Date().getFullYear()}
        </p>
      </footer>
    </div>
  )
}

export default App
