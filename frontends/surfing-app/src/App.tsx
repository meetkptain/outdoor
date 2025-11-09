import { useEffect, useMemo, useState } from 'react'
import { Outlet } from 'react-router-dom'
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

export type SurfOutletContext = {
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

  const outletContext: SurfOutletContext = useMemo(
    () => ({
      branding,
      palette,
      status,
    }),
    [branding, palette, status],
  )

  return (
    <div
      className="surf-app"
      style={{
        background: `radial-gradient(circle at top, ${palette.primary}33, transparent 55%), linear-gradient(180deg, #0f172a, #020617 60%)`,
        color: palette.primaryForeground,
      }}
    >
      <header className="surf-header">
        <div>
          <p className="surf-tagline">Expériences Outdoor SaaS</p>
          <h1>Ocean Glide Surf</h1>
          <p className="surf-subtitle">
            Réserve ton cours de surf en quelques minutes et laisse notre équipe
            s&rsquo;occuper du reste.
          </p>
        </div>
        <div className="surf-header-palette">
          <span
            className="surf-palette-chip"
            style={{ background: palette.primary }}
          />
          <span
            className="surf-palette-chip"
            style={{ background: palette.secondary }}
          />
        </div>
      </header>

      <main className="surf-main">
        {status === 'error' ? (
          <div className="surf-error">
            <h2>Oups, impossible de charger le branding</h2>
            <p>{error}</p>
          </div>
        ) : (
          <Outlet context={outletContext} />
        )}
      </main>

      <footer className="surf-footer">
        <p>
          Powered by Parapente SaaS · Multi-niche platform ·{' '}
          {new Date().getFullYear()}
        </p>
      </footer>
    </div>
  )
}

export default App
