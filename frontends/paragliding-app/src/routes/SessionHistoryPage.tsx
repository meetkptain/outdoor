import { useEffect, useMemo, useState } from 'react'
import { Link, useOutletContext } from 'react-router-dom'
import {
  Alert,
  Button,
  Card,
  Loader,
  apiClient,
  type AxiosResponse,
} from '@parapente/shared'
import { useParaglidingActivity } from '../hooks/useParaglidingActivity'
import type { ParaglidingOutletContext } from '../App'
import './SessionHistoryPage.css'

type SessionStatus =
  | 'scheduled'
  | 'completed'
  | 'cancelled'
  | 'pending_payment'
  | 'upsell_pending'
  | 'upsell_confirmed'

interface SessionHistoryEntry {
  uuid: string
  date: string
  window: string
  pilot: string | null
  weight_kg?: number
  status: SessionStatus
  shuttle_pickup?: string | null
  observers?: number
  wants_photos?: boolean
  upsell_offers?: UpsellOffer[]
  notes?: string | null
}

interface UpsellOffer {
  id: string
  name: string
  description?: string
  price: number
  recommended?: boolean
  status?: 'pending' | 'accepted' | 'declined'
}

type HistoryResponse =
  | SessionHistoryEntry[]
  | {
      success?: boolean
      data?: SessionHistoryEntry[]
    }

type UpsellResponse =
  | {
      success: boolean
      message?: string
    }
  | {
      error: string
    }

function formatDate(dateIso: string) {
  const parsed = new Date(dateIso)
  if (Number.isNaN(parsed.getTime())) {
    return dateIso
  }
  return parsed.toLocaleDateString('fr-FR', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
}

function formatStatus(status: SessionStatus) {
  switch (status) {
    case 'scheduled':
      return 'Planifié'
    case 'completed':
      return 'Terminé'
    case 'cancelled':
      return 'Annulé'
    case 'pending_payment':
      return 'Paiement en attente'
    case 'upsell_pending':
      return 'Offre en attente'
    case 'upsell_confirmed':
      return 'Pack confirmé'
    default:
      return status
  }
}

function formatWindow(window: string) {
  switch (window) {
    case 'morning':
      return 'Matin (8h - 11h)'
    case 'afternoon':
      return 'Après-midi (12h - 16h)'
    case 'sunset':
      return 'Coucher de soleil (17h - 20h)'
    default:
      return window
  }
}

function formatCurrencyEUR(value: number) {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'EUR',
    minimumFractionDigits: 0,
  }).format(value)
}

export default function SessionHistoryPage() {
  const { palette, branding, status: brandingStatus } =
    useOutletContext<ParaglidingOutletContext>()
  const { activity } = useParaglidingActivity()
  const [history, setHistory] = useState<SessionHistoryEntry[]>([])
  const [status, setStatus] = useState<'idle' | 'loading' | 'error' | 'ready'>(
    'idle',
  )
  const [error, setError] = useState<string | null>(null)
  const [actionFeedback, setActionFeedback] = useState<
    { sessionId: string; message: string; variant: 'success' | 'danger' } | null
  >(null)
  const [actionLoading, setActionLoading] = useState<string | null>(null)

  useEffect(() => {
    let cancelled = false

    async function fetchHistory() {
      setStatus('loading')
      setError(null)
      try {
        const response: AxiosResponse<HistoryResponse> = await apiClient.get(
          '/paragliding/sessions/history',
          {
            params: {
              activity_id: activity?.id,
            },
          },
        )

        if (cancelled) return

        const payload =
          Array.isArray(response.data) && response.data.length > 0
            ? response.data
            : 'data' in response.data &&
                Array.isArray((response.data as { data?: unknown }).data)
              ? ((response.data as { data?: SessionHistoryEntry[] }).data ??
                [])
              : []

        setHistory(payload)
        setStatus('ready')
      } catch (err) {
        if (cancelled) return
        setStatus('error')
        setError(
          err instanceof Error
            ? err.message
            : 'Impossible de récupérer l’historique des vols.',
        )
      }
    }

    fetchHistory()

    return () => {
      cancelled = true
    }
  }, [activity?.id])

  const upcoming = useMemo(
    () =>
      history.filter((session) =>
        ['scheduled', 'pending_payment', 'upsell_pending'].includes(
          session.status,
        ),
      ),
    [history],
  )

  const completed = useMemo(
    () =>
      history.filter((session) =>
        ['completed', 'upsell_confirmed'].includes(session.status),
      ),
    [history],
  )

  const cancelled = useMemo(
    () => history.filter((session) => session.status === 'cancelled'),
    [history],
  )

  async function handleUpsellAction(
    session: SessionHistoryEntry,
    offer: UpsellOffer,
    action: 'accept' | 'decline',
  ) {
    setActionFeedback(null)
    setActionLoading(`${session.uuid}:${offer.id}:${action}`)
    try {
      const response: AxiosResponse<UpsellResponse> = await apiClient.post(
        `/paragliding/sessions/${session.uuid}/upsell`,
        {
          offer_id: offer.id,
          action,
        },
      )

      const payload = response.data
      const success =
        typeof payload === 'object' &&
        payload !== null &&
        'success' in payload &&
        (payload as { success?: boolean }).success

      if (success) {
        setHistory((prev) =>
          prev.map((entry) =>
            entry.uuid === session.uuid
              ? {
                  ...entry,
                  status:
                    action === 'accept' ? 'upsell_confirmed' : entry.status,
                  upsell_offers: entry.upsell_offers?.map((existing) =>
                    existing.id === offer.id
                      ? {
                          ...existing,
                          status: action === 'accept' ? 'accepted' : 'declined',
                        }
                      : existing,
                  ),
                }
              : entry,
          ),
        )
        setActionFeedback({
          sessionId: session.uuid,
          message:
            action === 'accept'
              ? 'Pack ajouté à ta réservation.'
              : 'Merci pour ta réponse, nous n’ajoutons pas ce pack.',
          variant: 'success',
        })
      } else {
        const errorMessage =
          typeof payload === 'object' &&
          payload !== null &&
          'error' in payload &&
          typeof (payload as { error?: unknown }).error === 'string'
            ? (payload as { error: string }).error
            : 'Impossible de mettre à jour cette option.'
        setActionFeedback({
          sessionId: session.uuid,
          message: errorMessage,
          variant: 'danger',
        })
      }
    } catch (err) {
      setActionFeedback({
        sessionId: session.uuid,
        message:
          err instanceof Error
            ? err.message
            : 'Impossible de mettre à jour cette option.',
        variant: 'danger',
      })
    } finally {
      setActionLoading(null)
    }
  }

  if (status === 'loading' || brandingStatus === 'loading') {
    return (
      <div className="history-shell">
        <Loader label="Chargement de l’historique des vols..." />
      </div>
    )
  }

  if (status === 'error') {
    return (
      <div className="history-shell">
        <Alert
          variant="danger"
          heading="Historique indisponible"
          description={error}
        />
      </div>
    )
  }

  return (
    <div className="history-shell">
      <header className="history-header">
        <div>
          <h1>Historique de vols</h1>
          <p>
            Suis la préparation de tes sessions parapente, consulte le pilote
            assigné et ajoute les packs optionnels recommandés.
          </p>
        </div>
        <div className="history-actions">
          <Link to="/" className="history-link-button">
            Réserver un nouveau vol
          </Link>
        </div>
      </header>

      {actionFeedback && (
        <Alert
          variant={actionFeedback.variant}
          description={actionFeedback.message}
        />
      )}

      <section className="history-section">
        <h2>Prochaine(s) session(s)</h2>
        {upcoming.length === 0 ? (
          <Card>
            <p>
              Aucun vol planifié pour le moment. Réserve un nouveau créneau pour
              profiter des conditions idéales.
            </p>
          </Card>
        ) : (
          <div className="history-grid">
            {upcoming.map((session) => (
              <Card key={session.uuid} heading={formatDate(session.date)}>
                <dl className="history-details">
                  <div>
                    <dt>Statut</dt>
                    <dd>{formatStatus(session.status)}</dd>
                  </div>
                  <div>
                    <dt>Créneau</dt>
                    <dd>{formatWindow(session.window)}</dd>
                  </div>
                  <div>
                    <dt>Pilote</dt>
                    <dd>{session.pilot ?? 'En cours d’assignation'}</dd>
                  </div>
                  {session.weight_kg && (
                    <div>
                      <dt>Poids déclaré</dt>
                      <dd>{session.weight_kg} kg</dd>
                    </div>
                  )}
                  {session.shuttle_pickup && (
                    <div>
                      <dt>Navette</dt>
                      <dd>
                        {session.shuttle_pickup}
                        {session.observers
                          ? ` · ${session.observers} accompagnateur(s)`
                          : ''}
                      </dd>
                    </div>
                  )}
                  {session.notes && (
                    <div>
                      <dt>Notes</dt>
                      <dd>{session.notes}</dd>
                    </div>
                  )}
                </dl>

                {session.upsell_offers && session.upsell_offers.length > 0 && (
                  <div className="history-upsell">
                    <h3>Recommandé pour ce vol</h3>
                    <ul>
                      {session.upsell_offers.map((offer) => (
                        <li key={offer.id}>
                          <div>
                            <strong>{offer.name}</strong>{' '}
                            <span>{formatCurrencyEUR(offer.price)}</span>
                            {offer.recommended && (
                              <span className="history-flag">Populaire</span>
                            )}
                          </div>
                          {offer.description && <p>{offer.description}</p>}
                          <div className="history-upsell-actions">
                            <Button
                              size="sm"
                              onClick={() =>
                                handleUpsellAction(session, offer, 'accept')
                              }
                              isLoading={
                                actionLoading ===
                                `${session.uuid}:${offer.id}:accept`
                              }
                              disabled={offer.status === 'accepted'}
                            >
                              {offer.status === 'accepted'
                                ? 'Pack ajouté'
                                : 'Ajouter au vol'}
                            </Button>
                            <Button
                              size="sm"
                              variant="secondary"
                              onClick={() =>
                                handleUpsellAction(session, offer, 'decline')
                              }
                              isLoading={
                                actionLoading ===
                                `${session.uuid}:${offer.id}:decline`
                              }
                              disabled={offer.status === 'declined'}
                            >
                              {offer.status === 'declined'
                                ? 'Refusé'
                                : 'Plus tard'}
                            </Button>
                          </div>
                        </li>
                      ))}
                    </ul>
                  </div>
                )}
              </Card>
            ))}
          </div>
        )}
      </section>

      <section className="history-section">
        <h2>Vols réalisés</h2>
        {completed.length === 0 ? (
          <Card>
            <p>
              Dès qu’un vol est terminé, son résumé apparaît ici avec le pack
              photo et le pilote associé.
            </p>
          </Card>
        ) : (
          <div className="history-grid">
            {completed.map((session) => (
              <Card key={session.uuid} heading={formatDate(session.date)}>
                <dl className="history-details">
                  <div>
                    <dt>Statut</dt>
                    <dd>{formatStatus(session.status)}</dd>
                  </div>
                  <div>
                    <dt>Pilote</dt>
                    <dd>{session.pilot ?? 'Non renseigné'}</dd>
                  </div>
                  <div>
                    <dt>Photos/Vidéo</dt>
                    <dd>{session.wants_photos ? 'Inclues' : 'Non'}</dd>
                  </div>
                </dl>
                {session.upsell_offers && session.upsell_offers.length > 0 && (
                  <div className="history-upsell">
                    <h3>Offres proposées</h3>
                    <ul>
                      {session.upsell_offers.map((offer) => (
                        <li key={offer.id}>
                          <div>
                            <strong>{offer.name}</strong>{' '}
                            <span>{formatCurrencyEUR(offer.price)}</span>
                          </div>
                          <p>
                            Statut:{' '}
                            {offer.status === 'accepted'
                              ? 'Accepté'
                              : offer.status === 'declined'
                                ? 'Refusé'
                                : 'Sans réponse'}
                          </p>
                        </li>
                      ))}
                    </ul>
                  </div>
                )}
              </Card>
            ))}
          </div>
        )}
      </section>

      <section className="history-section">
        <h2>Sessions annulées</h2>
        {cancelled.length === 0 ? (
          <Card>
            <p>Aucune annulation récente.</p>
          </Card>
        ) : (
          <div className="history-grid">
            {cancelled.map((session) => (
              <Card key={session.uuid} heading={formatDate(session.date)}>
                <dl className="history-details">
                  <div>
                    <dt>Statut</dt>
                    <dd>{formatStatus(session.status)}</dd>
                  </div>
                  <div>
                    <dt>Pilote</dt>
                    <dd>{session.pilot ?? 'Non assigné'}</dd>
                  </div>
                  {session.notes && (
                    <div>
                      <dt>Notes</dt>
                      <dd>{session.notes}</dd>
                    </div>
                  )}
                </dl>
              </Card>
            ))}
          </div>
        )}
      </section>
    </div>
  )
}


