import { useEffect, useMemo, useState } from 'react'
import { useOutletContext } from 'react-router-dom'
import {
  Alert,
  Button,
  Card,
  FormField,
  Loader,
  apiClient,
  BOOKING_COPY,
  getBookingStepLabel,
} from '@parapente/shared'
import type { AxiosResponse } from '@parapente/shared'
import { useParaglidingActivity } from '../hooks/useParaglidingActivity'
import type { Activity } from '../types/activity'
import type { ParaglidingOutletContext } from '../App'
import {
  DEFAULT_PAYMENT_TYPE,
  PARAGLIDING_ACTIVITY_TYPE,
} from '../config/env'
import { formatCurrency } from '../utils/currency'
import './BookingPage.css'

type Step = 'intro' | 'details' | 'review' | 'success'
type ShuttleStatus = 'idle' | 'checking' | 'available' | 'unavailable' | 'error'

interface CustomerInfo {
  firstName: string
  lastName: string
  email: string
  phone: string
}

interface BookingFormState {
  customer: CustomerInfo
  flightDate: string
  flightWindow: string
  weightKg: string
  hasMedicalRestriction: boolean
  needsShuttle: boolean
  shuttlePickup: string
  observerCount: number
  wantsPhotoPack: boolean
  preferredPilot: string
  notes: string
}

interface ReservationPayload {
  reservation?: {
    uuid: string
    status: string
  }
  success?: boolean
  message?: string
}

const DEFAULT_FLIGHT_WINDOWS = ['morning', 'afternoon', 'sunset']
const DEFAULT_MAX_OBSERVERS = 3

function getNumericMetadata(
  activity: Activity | null,
  key: string,
  fallback = 0,
): number {
  const value = activity?.metadata?.[key as keyof Activity['metadata']]
  if (typeof value === 'number') {
    return value
  }
  if (typeof value === 'string') {
    const parsed = Number.parseFloat(value)
    return Number.isFinite(parsed) ? parsed : fallback
  }
  return fallback
}

function getFlightWindows(activity: Activity | null): string[] {
  const raw = activity?.metadata?.flight_windows
  if (Array.isArray(raw)) {
    return raw
      .map((entry) => {
        if (typeof entry === 'string') {
          return entry
        }
        if (entry && typeof entry === 'object' && 'value' in entry) {
          const value = (entry as { value?: unknown }).value
          return typeof value === 'string' ? value : ''
        }
        return ''
      })
      .filter((value): value is string => value.length > 0)
  }
  return DEFAULT_FLIGHT_WINDOWS
}

function formatFlightWindow(window: string): string {
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

function calculatePricing(activity: Activity | null) {
  const pricing = activity?.pricing_config
  if (!pricing) {
    return { total: 0, deposit: 0 }
  }

  if (pricing.model === 'per_participant') {
    const base = pricing.base_price ?? 0
    const deposit = pricing.deposit_amount ?? base * 0.3
    return {
      total: base,
      deposit,
    }
  }

  if (pricing.model === 'tiered' && pricing.tiers?.length) {
    const firstTier = pricing.tiers.find((tier) => tier.up_to >= 1)
    const price = firstTier?.price ?? pricing.tiers[0]?.price ?? 0
    const deposit =
      pricing.deposit_amount !== undefined
        ? pricing.deposit_amount
        : price * 0.3
    return {
      total: price,
      deposit,
    }
  }

  const total = pricing.base_price ?? 0
  const deposit =
    pricing.deposit_amount !== undefined ? pricing.deposit_amount : total * 0.3
  return { total, deposit }
}

function getDefaultState(activity?: Activity | null): BookingFormState {
  const windows = getFlightWindows(activity ?? null)
  return {
    customer: {
      firstName: '',
      lastName: '',
      email: '',
      phone: '',
    },
    flightDate: '',
    flightWindow: windows[0] ?? '',
    weightKg: '',
    hasMedicalRestriction: false,
    needsShuttle: false,
    shuttlePickup: '',
    observerCount: 0,
    wantsPhotoPack: false,
    preferredPilot: '',
    notes: '',
  }
}

export default function BookingPage() {
  const { palette, status: brandingStatus } =
    useOutletContext<ParaglidingOutletContext>()
  const { activity, status, error } = useParaglidingActivity()
  const [step, setStep] = useState<Step>('intro')
  const [form, setForm] = useState<BookingFormState>(() => getDefaultState())
  const [submitStatus, setSubmitStatus] = useState<
    'idle' | 'loading' | 'success' | 'error'
  >('idle')
  const [submitError, setSubmitError] = useState<string | null>(null)
  const [reservationRef, setReservationRef] = useState<string | null>(null)
  const [shuttleStatus, setShuttleStatus] = useState<ShuttleStatus>('idle')
  const [shuttleSuggestion, setShuttleSuggestion] = useState<string | null>(
    null,
  )

  const flightWindows = useMemo(
    () => getFlightWindows(activity ?? null),
    [activity],
  )

  const maxObservers = useMemo(() => {
    const derived = getNumericMetadata(activity ?? null, 'max_observers')
    if (Number.isFinite(derived) && derived >= 0) {
      return Math.floor(derived)
    }
    return DEFAULT_MAX_OBSERVERS
  }, [activity])

  const maxWeight = useMemo(
    () => getNumericMetadata(activity ?? null, 'max_total_weight'),
    [activity],
  )
  const recommendedWeight = useMemo(
    () => getNumericMetadata(activity ?? null, 'recommended_weight'),
    [activity],
  )

  const pilotRoster = useMemo(() => {
    const raw = activity?.metadata?.pilot_roster
    if (Array.isArray(raw)) {
      return raw
        .map((pilot) => (typeof pilot === 'string' ? pilot.trim() : ''))
        .filter((pilot) => pilot.length > 0)
    }
    return []
  }, [activity])

  useEffect(() => {
    setForm((prev) => {
      const defaults = getDefaultState(activity)
      const nextWindow =
        prev.flightWindow && flightWindows.includes(prev.flightWindow)
          ? prev.flightWindow
          : defaults.flightWindow
      return {
        ...prev,
        flightWindow: nextWindow,
      }
    })
  }, [activity, flightWindows])

  useEffect(() => {
    let cancelled = false

    if (!form.needsShuttle) {
      setShuttleStatus('idle')
      setShuttleSuggestion(null)
      return
    }

    if (!form.flightDate) {
      setShuttleStatus('idle')
      setShuttleSuggestion(null)
      return
    }

    async function checkAvailability() {
      setShuttleStatus('checking')
      try {
        const response: AxiosResponse<{ available?: boolean; next_slot?: string }> =
          await apiClient.get('/paragliding/shuttles/availability', {
            params: {
              date: form.flightDate,
              window: form.flightWindow || undefined,
              seats: 1 + form.observerCount,
            },
          })

        if (cancelled) {
          return
        }

        const payload = response.data ?? {}
        const available =
          typeof payload === 'object' && payload !== null
            ? Boolean((payload as { available?: boolean }).available)
            : false
        setShuttleStatus(available ? 'available' : 'unavailable')
        const nextSlot =
          typeof (payload as { next_slot?: unknown }).next_slot === 'string'
            ? (payload as { next_slot?: string }).next_slot!
            : null
        setShuttleSuggestion(nextSlot)
      } catch {
        if (!cancelled) {
          setShuttleStatus('error')
        }
      }
    }

    checkAvailability()

    return () => {
      cancelled = true
    }
  }, [form.needsShuttle, form.flightDate, form.flightWindow, form.observerCount])

  const priceSummary = useMemo(
    () => calculatePricing(activity ?? null),
    [activity],
  )

  const isLoading = status === 'loading' || brandingStatus === 'loading'

  const passengerWeight = Number.parseFloat(form.weightKg)
  const hasWeightValue = Number.isFinite(passengerWeight) && passengerWeight > 0
  const weightTooHigh = hasWeightValue && maxWeight > 0 && passengerWeight > maxWeight
  const weightAboveRecommended =
    hasWeightValue &&
    !weightTooHigh &&
    recommendedWeight > 0 &&
    passengerWeight > recommendedWeight

  const shuttleBlocked =
    form.needsShuttle &&
    (shuttleStatus === 'checking' || shuttleStatus === 'unavailable')

  const requiredFieldsValid =
    form.customer.firstName.trim() &&
    form.customer.lastName.trim() &&
    form.customer.email.trim() &&
    form.customer.phone.trim() &&
    form.flightDate &&
    form.flightWindow &&
    hasWeightValue &&
    !weightTooHigh &&
    (!form.needsShuttle || form.shuttlePickup.trim()) &&
    !shuttleBlocked

  async function handleSubmit() {
    if (!activity) return

    setSubmitStatus('loading')
    setSubmitError(null)

    const payload = {
      activity_id: activity.id,
      activity_type: activity.activity_type ?? PARAGLIDING_ACTIVITY_TYPE,
      customer_first_name: form.customer.firstName.trim(),
      customer_last_name: form.customer.lastName.trim(),
      customer_email: form.customer.email.trim(),
      customer_phone: form.customer.phone.trim(),
      participants_count: 1,
      metadata: {
        flight_date: form.flightDate || undefined,
        flight_window: form.flightWindow || undefined,
        passenger_weight_kg: hasWeightValue ? passengerWeight : undefined,
        medical_concerns: form.hasMedicalRestriction,
        needs_shuttle: form.needsShuttle,
        shuttle_status: form.needsShuttle ? shuttleStatus : 'not_requested',
        shuttle_next_slot:
          form.needsShuttle && shuttleSuggestion ? shuttleSuggestion : undefined,
        pickup_location: form.needsShuttle ? form.shuttlePickup || undefined : undefined,
        observer_count: form.needsShuttle ? form.observerCount : 0,
        wants_photos: form.wantsPhotoPack,
        preferred_pilot: form.preferredPilot || undefined,
        notes: form.notes || undefined,
        activity_source: 'paragliding_app',
      },
      payment_type: DEFAULT_PAYMENT_TYPE,
    }

    try {
      const response: AxiosResponse<
        ReservationPayload | { success?: boolean; data?: ReservationPayload }
      > = await apiClient.post('/reservations', payload)

      const result =
        'data' in response.data && response.data.data
          ? response.data.data
          : response.data

      setSubmitStatus('success')
      setStep('success')
      setReservationRef(result?.reservation?.uuid ?? null)
    } catch (err) {
      setSubmitStatus('error')
      setSubmitError(
        err instanceof Error
          ? err.message
          : 'Impossible de confirmer la réservation',
      )
    }
  }

  const takeoffSite =
    typeof activity?.metadata?.default_takeoff_site === 'string'
      ? activity.metadata.default_takeoff_site
      : 'nos sites en Savoie'
  const flightDuration =
    typeof activity?.duration_minutes === 'number'
      ? `${activity.duration_minutes} minutes de vol`
      : 'Vol de 20 à 30 minutes'

  if (isLoading && step === 'intro') {
    return (
      <div className="booking-shell">
        <Loader label="Chargement de l'activité parapente..." />
      </div>
    )
  }

  if (status === 'error') {
    return (
      <div className="booking-shell">
        <Alert
          variant="danger"
          heading="Activité indisponible"
          description={error}
        />
      </div>
    )
  }

  return (
    <div className="booking-shell">
      <section className="booking-card">
        <header className="booking-header">
          <div>
            <p className="booking-step">
              <span>{getBookingStepLabel(step)}</span>
            </p>
            <h2>{activity?.name ?? 'Baptême parapente biplace'}</h2>
            <p>
              {activity?.description ??
                'Briefing sécurité, décollage accompagné et suivi météo en direct pour garantir la meilleure fenêtre de vol.'}
            </p>
          </div>
          <div className="booking-price">
            <strong>{formatCurrency(priceSummary.total)}</strong>
            <span>
              acompte {formatCurrency(priceSummary.deposit)} · 1 passager
            </span>
          </div>
        </header>

        {step === 'intro' && (
          <div className="booking-intro">
            <Card
              heading="Ce qui est inclus"
              subheading={`${flightDuration}, équipement fourni, suivi météo & logistique navette.`}
            >
              <ul className="booking-list">
                <li>Décollage depuis {takeoffSite}</li>
                <li>
                  Briefing sécurité + équipement complet (casque, sellette,
                  coupe-vent)
                </li>
                <li>
                  Navette sous réserve de disponibilité. Confirmation la veille
                  selon la météo.
                </li>
              </ul>
            </Card>

            <div className="booking-cta">
              <Button size="lg" onClick={() => setStep('details')}>
                Préparer mon vol
              </Button>
            </div>
          </div>
        )}

        {step === 'details' && (
          <div className="booking-form">
            <div className="booking-form-grid">
              <FormField label="Prénom" required>
                <input
                  type="text"
                  value={form.customer.firstName}
                  onChange={(event) =>
                    setForm((prev) => ({
                      ...prev,
                      customer: {
                        ...prev.customer,
                        firstName: event.target.value,
                      },
                    }))
                  }
                  placeholder="Léa"
                />
              </FormField>
              <FormField label="Nom" required>
                <input
                  type="text"
                  value={form.customer.lastName}
                  onChange={(event) =>
                    setForm((prev) => ({
                      ...prev,
                      customer: {
                        ...prev.customer,
                        lastName: event.target.value,
                      },
                    }))
                  }
                  placeholder="Vague"
                />
              </FormField>
            </div>

            <div className="booking-form-grid">
              <FormField label="Email" required>
                <input
                  type="email"
                  value={form.customer.email}
                  onChange={(event) =>
                    setForm((prev) => ({
                      ...prev,
                      customer: {
                        ...prev.customer,
                        email: event.target.value,
                      },
                    }))
                  }
                  placeholder="lea@example.com"
                />
              </FormField>
              <FormField label="Téléphone" required>
                <input
                  type="tel"
                  value={form.customer.phone}
                  onChange={(event) =>
                    setForm((prev) => ({
                      ...prev,
                      customer: {
                        ...prev.customer,
                        phone: event.target.value,
                      },
                    }))
                  }
                  placeholder="+33600000000"
                />
              </FormField>
            </div>

            <div className="booking-form-grid">
              <FormField label="Date de vol souhaitée" required>
                <input
                  type="date"
                  value={form.flightDate}
                  onChange={(event) =>
                    setForm((prev) => ({
                      ...prev,
                      flightDate: event.target.value,
                    }))
                  }
                />
              </FormField>
              <FormField label="Créneau de vol" required>
                <select
                  value={form.flightWindow}
                  onChange={(event) =>
                    setForm((prev) => ({
                      ...prev,
                      flightWindow: event.target.value,
                    }))
                  }
                >
                  <option value="">Sélectionner</option>
                  {flightWindows.map((window) => (
                    <option key={window} value={window}>
                      {formatFlightWindow(window)}
                    </option>
                  ))}
                </select>
              </FormField>
            </div>

            <div className="booking-form-grid">
              <FormField
                label="Poids passager (kg)"
                required
                hint={
                  maxWeight > 0
                    ? `Maximum ${maxWeight} kg`
                    : 'Entre 40 et 120 kg'
                }
              >
                <input
                  type="number"
                  min={30}
                  max={maxWeight || undefined}
                  step="0.5"
                  value={form.weightKg}
                  onChange={(event) =>
                    setForm((prev) => ({
                      ...prev,
                      weightKg: event.target.value,
                    }))
                  }
                  placeholder="80"
                />
              </FormField>

              <FormField label="Préférence pilote">
                <select
                  value={form.preferredPilot}
                  onChange={(event) =>
                    setForm((prev) => ({
                      ...prev,
                      preferredPilot: event.target.value,
                    }))
                  }
                >
                  <option value="">Aucune préférence</option>
                  {pilotRoster.map((pilot) => (
                    <option key={pilot} value={pilot}>
                      {pilot}
                    </option>
                  ))}
                </select>
              </FormField>
            </div>

            {weightTooHigh && (
              <Alert
                variant="danger"
                heading="Poids supérieur à la limite"
                description="Pour des raisons de sécurité, le poids maximum autorisé est dépassé. Contacte l’équipe pour organiser un vol adapté."
              />
            )}

            {!weightTooHigh && weightAboveRecommended && (
              <Alert
                variant="warning"
                heading="Au-delà du poids recommandé"
                description="Le vol reste possible, mais peut nécessiter un créneau précis ou un pilote spécifique. Nous te confirmerons la meilleure option."
              />
            )}

            <div className="booking-flags">
              <label className="booking-flag">
                <input
                  type="checkbox"
                  checked={form.wantsPhotoPack}
                  onChange={(event) =>
                    setForm((prev) => ({
                      ...prev,
                      wantsPhotoPack: event.target.checked,
                    }))
                  }
                />
                <span>Inclure le pack photo / vidéo (+80€)</span>
              </label>
              <label className="booking-flag">
                <input
                  type="checkbox"
                  checked={form.hasMedicalRestriction}
                  onChange={(event) =>
                    setForm((prev) => ({
                      ...prev,
                      hasMedicalRestriction: event.target.checked,
                    }))
                  }
                />
                <span>Je signale une contrainte médicale</span>
              </label>
            </div>

            <div className="booking-shuttle">
              <label className="booking-flag">
                <input
                  type="checkbox"
                  checked={form.needsShuttle}
                  onChange={(event) =>
                    setForm((prev) => ({
                      ...prev,
                      needsShuttle: event.target.checked,
                    }))
                  }
                />
                <span>Navette décollage nécessaire</span>
              </label>

              {form.needsShuttle && (
                <>
                  <div className="booking-form-grid">
                    <FormField label="Lieu de prise en charge" required>
                      <input
                        type="text"
                        value={form.shuttlePickup}
                        onChange={(event) =>
                          setForm((prev) => ({
                            ...prev,
                            shuttlePickup: event.target.value,
                          }))
                        }
                        placeholder="Gare de Talloires"
                      />
                    </FormField>
                    <FormField
                      label="Accompagnateurs"
                      hint={`Jusqu'à ${maxObservers} personne(s)`}
                    >
                      <input
                        type="number"
                        min={0}
                        max={maxObservers}
                        value={form.observerCount}
                        onChange={(event) => {
                          const parsed = Number.parseInt(event.target.value, 10)
                          const next = Number.isFinite(parsed) ? parsed : 0
                          setForm((prev) => ({
                            ...prev,
                            observerCount: Math.min(
                              Math.max(0, next),
                              maxObservers,
                            ),
                          }))
                        }}
                      />
                    </FormField>
                  </div>

                  <div className="booking-shuttle-status">
                    {shuttleStatus === 'checking' && (
                      <p className="status-checking">
                        Vérification de la disponibilité navette...
                      </p>
                    )}
                    {shuttleStatus === 'available' && (
                      <p className="status-available">
                        Navette disponible
                        {shuttleSuggestion
                          ? ` · prochain départ ${shuttleSuggestion}`
                          : ''}.
                      </p>
                    )}
                    {shuttleStatus === 'unavailable' && (
                      <Alert
                        variant="danger"
                        description="Navette complète sur ce créneau. Choisis un autre créneau ou contacte-nous pour une solution."
                      />
                    )}
                    {shuttleStatus === 'error' && (
                      <Alert
                        variant="warning"
                        description="Nous n'avons pas pu vérifier la navette automatiquement. L'équipe confirmera la logistique rapidement."
                      />
                    )}
                  </div>
                </>
              )}
            </div>

            <FormField
              label="Notes"
              hint="Allergies, souhait météo, taille de combinaison, etc."
            >
              <textarea
                value={form.notes}
                onChange={(event) =>
                  setForm((prev) => ({
                    ...prev,
                    notes: event.target.value,
                  }))
                }
                placeholder="Ex : en couple, préférence pour un créneau coucher de soleil."
                rows={3}
              />
            </FormField>

            <div className="booking-actions">
              <Button variant="secondary" onClick={() => setStep('intro')}>
                Retour
              </Button>
              <Button onClick={() => setStep('review')} disabled={!requiredFieldsValid}>
                {BOOKING_COPY.CTA_CONTINUE}
              </Button>
            </div>
          </div>
        )}

        {step === 'review' && (
          <div className="booking-review">
            <Card heading="Récapitulatif">
              <dl className="booking-summary">
                <div>
                  <dt>Contact</dt>
                  <dd>
                    {form.customer.firstName} {form.customer.lastName}
                    <br />
                    {form.customer.email}
                    <br />
                    {form.customer.phone}
                  </dd>
                </div>
                <div>
                  <dt>Détails vol</dt>
                  <dd>
                    Date : {form.flightDate || 'à préciser'}
                    <br />
                    Créneau :{' '}
                    {form.flightWindow
                      ? formatFlightWindow(form.flightWindow)
                      : 'à préciser'}
                    <br />
                    Poids passager :{' '}
                    {hasWeightValue ? `${passengerWeight} kg` : 'à confirmer'}
                    <br />
                    Navette : {form.needsShuttle ? 'Oui' : 'Non'}
                    {form.needsShuttle && (
                      <>
                        <br />
                        Prise en charge : {form.shuttlePickup || 'à définir'}
                        <br />
                        Accompagnateurs : {form.observerCount}
                      </>
                    )}
                  </dd>
                </div>
                <div>
                  <dt>Options</dt>
                  <dd>
                    Pack photo / vidéo : {form.wantsPhotoPack ? 'Oui' : 'Non'}
                    <br />
                    Contrainte médicale :{' '}
                    {form.hasMedicalRestriction ? 'Oui' : 'Non'}
                    <br />
                    Pilote préféré :{' '}
                    {form.preferredPilot ? form.preferredPilot : 'Aucun'}
                  </dd>
                </div>
                <div>
                  <dt>Montant total</dt>
                  <dd>
                    {formatCurrency(priceSummary.total)}{' '}
                    <span className="booking-summary-deposit">
                      acompte {formatCurrency(priceSummary.deposit)}
                    </span>
                  </dd>
                </div>
              </dl>
              {form.notes && (
                <div className="booking-notes">
                  <strong>Notes :</strong> {form.notes}
                </div>
              )}
            </Card>

            {submitError && (
              <Alert
                variant="danger"
                heading="Erreur"
                description={submitError}
              />
            )}

            <div className="booking-actions">
              <Button variant="secondary" onClick={() => setStep('details')}>
                Modifier
              </Button>
              <Button onClick={handleSubmit} isLoading={submitStatus === 'loading'}>
                {BOOKING_COPY.CTA_CONFIRM}
              </Button>
            </div>
          </div>
        )}

        {step === 'success' && (
          <div className="booking-success">
            <Card heading={BOOKING_COPY.SUCCESS_PARAGLIDING_HEADING || BOOKING_COPY.SUCCESS_DEFAULT_HEADING}>
              <p>
                Notre équipe te recontacte sous 24h pour verrouiller la fenêtre
                météo idéale et confirmer le pilote.
              </p>
              {reservationRef && (
                <p className="booking-reference">
                  Référence de réservation : <strong>{reservationRef}</strong>
                </p>
              )}
              <Button onClick={() => setStep('intro')}>
                {BOOKING_COPY.CTA_NEW_REQUEST}
              </Button>
            </Card>
          </div>
        )}
      </section>
    </div>
  )
}


