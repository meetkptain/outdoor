import { useEffect, useMemo, useState } from 'react'
import { apiClient } from '@parapente/shared'
import type { AxiosResponse } from '@parapente/shared'
import { useOutletContext } from 'react-router-dom'
import type { AdminOutletContext } from '../App'
import './DashboardPage.css'

type SummaryPayload = Record<string, unknown>

type SummaryResponse =
  | {
      success?: boolean
      data?: SummaryPayload
    }
  | SummaryPayload

export default function DashboardPage() {
  const { fetchState, tenantState } = useOutletContext<AdminOutletContext>()
  const [status, setStatus] = useState<'idle' | 'loading' | 'ready' | 'error'>(
    'idle',
  )
  const [summary, setSummary] = useState<SummaryPayload | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!tenantState.token) {
      setStatus('idle')
      setSummary(null)
      return
    }

    setStatus('loading')
    apiClient
      .get<SummaryResponse>('/admin/dashboard/summary')
      .then((response: AxiosResponse<SummaryResponse>) => {
        const payload = (
          'data' in response.data && response.data.data
            ? response.data.data
            : response.data
        ) as SummaryPayload | undefined
        setSummary((payload ?? {}) as SummaryPayload)
        setStatus('ready')
      })
      .catch((err: unknown) => {
        const message =
          err instanceof Error
            ? err.message
            : 'Impossible de récupérer les indicateurs'
        setError(message)
        setStatus('error')
      })
  }, [tenantState.token])

  const metrics = useMemo(
    () =>
      Object.entries(summary ?? {}).filter(
        ([, value]) => typeof value === 'number' || typeof value === 'string',
      ),
    [summary],
  )

  const isLayoutReady = fetchState === 'ready'

  if (!tenantState.token) {
    return (
      <section className="panel">
        <h2>Tableau de bord</h2>
        <p>
          Connecte-toi pour visualiser les indicateurs multi-niche du tenant.
        </p>
      </section>
    )
  }

  return (
    <>
      <section className="panel">
        <h2>Statut plateforme</h2>
        <div className="layout-status">
          <span
            className={`status-dot status-${isLayoutReady ? 'ready' : 'pending'}`}
          />
          <p>
            Branding{' '}
            {isLayoutReady ? 'chargé : les couleurs sont disponibles.' : 'en cours.'}
          </p>
        </div>
      </section>

      {status === 'loading' && (
        <section className="panel">
          <p>Chargement des indicateurs…</p>
        </section>
      )}

      {status === 'error' && (
        <section className="panel panel-error">
          <h2>Erreur</h2>
          <p>{error}</p>
          <p>
            Vérifie que le rôle connecté possède l&apos;accès Dashboard (`/admin/dashboard/summary`).
          </p>
        </section>
      )}

      {status === 'ready' && (
        <section className="panel">
          <h2>Indicateurs clés</h2>
          {metrics.length === 0 ? (
            <p>Aucun indicateur renvoyé pour le moment.</p>
          ) : (
            <div className="metrics-grid">
              {metrics.map(([key, value]) => (
                <article key={key} className="metric-card">
                  <p className="metric-label">{key}</p>
                  <p className="metric-value">{String(value)}</p>
                </article>
              ))}
            </div>
          )}
        </section>
      )}
    </>
  )
}

