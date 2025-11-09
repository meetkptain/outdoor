import { useEffect, useMemo, useState } from 'react'
import { apiClient } from '@parapente/shared'
import type { AxiosResponse } from '@parapente/shared'
import { useOutletContext } from 'react-router-dom'
import type { AdminOutletContext } from '../App'
import './ReservationsPage.css'

type ReservationRecord = {
  id: number
  uuid: string
  status: string
  activity_type?: string
  activity?: {
    id?: number
    display_name?: string
    activity_type?: string
  }
  participants_count?: number
  scheduled_at?: string | null
  instructor?: {
    id?: number
    full_name?: string
  }
  customer_first_name?: string
  customer_last_name?: string
  metadata?: Record<string, unknown>
  created_at?: string
}

type PaginatedReservations = {
  data?: ReservationRecord[]
  pagination?: {
    current_page?: number
    per_page?: number
    total?: number
    last_page?: number
  }
}

type ReservationResponse =
  | {
      success?: boolean
      data?: PaginatedReservations
    }
  | PaginatedReservations

export default function ReservationsPage() {
  const { tenantState } = useOutletContext<AdminOutletContext>()
  const [status, setStatus] = useState<'idle' | 'loading' | 'ready' | 'error'>(
    'idle',
  )
  const [reservations, setReservations] = useState<ReservationRecord[]>([])
  const [pagination, setPagination] = useState<
    PaginatedReservations['pagination']
  >({})
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!tenantState.token) {
      setStatus('idle')
      setReservations([])
      setPagination({})
      return
    }

    setStatus('loading')
    apiClient
      .get<ReservationResponse>('/admin/reservations', {
        params: { per_page: 10 },
      })
      .then((response: AxiosResponse<ReservationResponse>) => {
        const payload = (
          'data' in response.data && response.data.data
            ? response.data.data
            : response.data
        ) as PaginatedReservations | undefined
        setReservations(payload?.data ?? [])
        setPagination(payload?.pagination ?? {})
        setStatus('ready')
      })
      .catch((err: unknown) => {
        const message =
          err instanceof Error
            ? err.message
            : 'Impossible de récupérer les réservations'
        setError(message)
        setStatus('error')
      })
  }, [tenantState.token])

  const totalReservations = pagination?.total ?? reservations.length

  const emptyState = useMemo(
    () =>
      status === 'ready' &&
      totalReservations === 0 && (
        <p className="empty-state">
          Aucune réservation pour le moment. Dès qu&apos;un client passera par le
          module multi-niche, vous verrez les flux apparaître ici.
        </p>
      ),
    [status, totalReservations],
  )

  if (!tenantState.token) {
    return (
      <section className="panel">
        <h2>Réservations</h2>
        <p>
          Connecte-toi pour consulter et gérer les réservations du tenant
          multi-niche.
        </p>
      </section>
    )
  }

  return (
    <section className="panel">
      <header className="panel-header">
        <div>
          <h2>Réservations</h2>
          <p className="panel-subtitle">
            Tenant{' '}
            {tenantState.organizationId
              ? `#${tenantState.organizationId}`
              : 'non défini'}
          </p>
        </div>
        <span className="badge">
          Total {totalReservations.toLocaleString('fr-FR')}
        </span>
      </header>

      {status === 'loading' && tenantState.token && (
        <p>Chargement des réservations…</p>
      )}

      {status === 'error' && tenantState.token && (
        <div className="panel panel-inline-error">
          <h3>Erreur</h3>
          <p>{error}</p>
          <p>
            Vérifie que le token possède le scope admin et que l&apos;API
            accepte le header X-Organization-ID courant.
          </p>
        </div>
      )}

      {tenantState.token ? emptyState : null}

      {status === 'ready' && tenantState.token && reservations.length > 0 && (
        <div className="table-wrapper">
          <table className="data-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Client</th>
                <th>Activité</th>
                <th>Participants</th>
                <th>Statut</th>
                <th>Instructeur</th>
                <th>Créée le</th>
              </tr>
            </thead>
            <tbody>
              {reservations.map((reservation) => (
                <tr key={reservation.id}>
                  <td>
                    <span className="mono">{reservation.uuid.slice(0, 8)}</span>
                  </td>
                  <td>
                    {reservation.customer_first_name
                      ? `${reservation.customer_first_name} ${reservation.customer_last_name ?? ''}`.trim()
                      : '—'}
                  </td>
                  <td>
                    {reservation.activity?.display_name ??
                      reservation.activity_type ??
                      '—'}
                  </td>
                  <td>{reservation.participants_count ?? 1}</td>
                  <td>
                    <span className={`status-chip status-${reservation.status}`}>
                      {reservation.status}
                    </span>
                  </td>
                  <td>
                    {reservation.instructor?.full_name
                      ? reservation.instructor.full_name
                      : 'Non assigné'}
                  </td>
                  <td>
                    {reservation.created_at
                      ? new Date(reservation.created_at).toLocaleString('fr-FR')
                      : '—'}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          {pagination?.total && pagination?.per_page && (
            <footer className="table-footer">
              <span>
                Page {pagination.current_page ?? 1} /{' '}
                {pagination.last_page ?? 1}
              </span>
              <span>{pagination.total} résultats</span>
            </footer>
          )}
        </div>
      )}
    </section>
  )
}

