import { useEffect, useMemo, useState } from 'react'
import { useOutletContext } from 'react-router-dom'
import {
  Alert,
  Button,
  Card,
  FormField,
  Loader,
  apiClient,
} from '@parapente/shared'
import type { AxiosResponse } from '@parapente/shared'
import { BOOKING_COPY, getBookingStepLabel } from '@parapente/shared'
import { useSurfActivity } from '../hooks/useSurfActivity'
import type { Activity } from '../types/activity'
import type { SurfOutletContext } from '../App'
import {
  DEFAULT_PAYMENT_TYPE,
  SURF_ACTIVITY_TYPE,
} from '../config/env'
import { formatCurrency } from '../utils/currency'
import './BookingPage.css'

type Step = 'intro' | 'details' | 'review' | 'success'

type SwimmingLevel = 'beginner' | 'intermediate' | 'advanced' | ''

interface ParticipantInput {
  name: string
}

interface CustomerInfo {
  firstName: string
  lastName: string
  email: string
  phone: string
}

interface BookingFormState {
  customer: CustomerInfo
  participantsCount: number
  swimmingLevel: SwimmingLevel
  equipmentRental: boolean
  participants: ParticipantInput[]
  notes: string
  desiredDate: string
  desiredTime: string
}

interface ReservationPayload {
  reservation?: {
    uuid: string
    status: string
  }
  success?: boolean
  message?: string
}

function getDefaultState(activity?: Activity | null): BookingFormState {
  const max =
    activity?.constraints_config?.participants?.max &&
    activity?.constraints_config?.participants?.max > 0
      ? activity.constraints_config.participants.max
      : 6
  const min =
    activity?.constraints_config?.participants?.min &&
    activity?.constraints_config?.participants?.min > 0
      ? activity.constraints_config.participants.min
      : 2
  const initialCount = Math.min(Math.max(4, min), max)

  return {
    customer: {
      firstName: '',
      lastName: '',
      email: '',
      phone: '',
    },
    participantsCount: initialCount,
    swimmingLevel: '',
    equipmentRental: true,
    participants: Array.from({ length: initialCount }, () => ({ name: '' })),
    notes: '',
    desiredDate: '',
    desiredTime: '',
  }
}

function calculatePricing(activity: Activity | null, count: number) {
  const pricing = activity?.pricing_config
  if (!pricing) {
    return { total: 0, deposit: 0 }
  }

  if (pricing.model === 'tiered' && pricing.tiers?.length) {
    const tier =
      pricing.tiers.find((t) => count <= t.up_to) ??
      pricing.tiers[pricing.tiers.length - 1]
    const total = tier ? tier.price * count : 0
    const deposit = pricing.deposit_amount
      ? pricing.deposit_amount * count
      : total * 0.3
    return { total, deposit }
  }

  if (pricing.model === 'per_participant') {
    const base = pricing.base_price ?? 0
    const total = base * count
    const deposit = pricing.deposit_amount ?? base * 0.3
    return {
      total,
      deposit: deposit * count,
    }
  }

  const total = pricing.base_price ?? 0
  const deposit = pricing.deposit_amount ?? total * 0.3
  return { total, deposit }
}

const SWIMMING_LEVELS: SwimmingLevel[] = [
  'beginner',
  'intermediate',
  'advanced',
  '',
]

export default function BookingPage() {
  const { status: brandingStatus } = useOutletContext<SurfOutletContext>()
  const { activity, status, error } = useSurfActivity()
  const [step, setStep] = useState<Step>('intro')
  const [form, setForm] = useState<BookingFormState>(() => getDefaultState())
  const [submitStatus, setSubmitStatus] = useState<
    'idle' | 'loading' | 'success' | 'error'
  >('idle')
  const [submitError, setSubmitError] = useState<string | null>(null)
  const [reservationRef, setReservationRef] = useState<string | null>(null)

  useEffect(() => {
    if (activity) {
      setForm((prev) => ({
        ...getDefaultState(activity),
        customer: prev.customer,
        notes: prev.notes,
      }))
    }
  }, [activity])

  const maxParticipants =
    activity?.constraints_config?.participants?.max ?? 6
  const minParticipants =
    activity?.constraints_config?.participants?.min ?? 2

  const priceSummary = useMemo(
    () => calculatePricing(activity ?? null, form.participantsCount),
    [activity, form.participantsCount],
  )

  const swimmingOptions =
    activity?.constraints_config?.enums?.swimming_level ??
    SWIMMING_LEVELS.filter((level) => level)

  const isLoading = status === 'loading' || brandingStatus === 'loading'

  function handleParticipantsCountChange(value: number) {
    setForm((prev) => {
      const nextParticipants = [...prev.participants]
      if (value > nextParticipants.length) {
        while (nextParticipants.length < value) {
          nextParticipants.push({ name: '' })
        }
      } else {
        nextParticipants.length = value
      }
      return {
        ...prev,
        participantsCount: value,
        participants: nextParticipants,
      }
    })
  }

  function handleParticipantNameChange(index: number, name: string) {
    setForm((prev) => {
      const nextParticipants = [...prev.participants]
      nextParticipants[index] = { name }
      return { ...prev, participants: nextParticipants }
    })
  }

  async function handleSubmit() {
    if (!activity) return

    setSubmitStatus('loading')
    setSubmitError(null)

    const payload = {
      activity_id: activity.id,
      activity_type: activity.activity_type ?? SURF_ACTIVITY_TYPE,
      customer_first_name: form.customer.firstName,
      customer_last_name: form.customer.lastName,
      customer_email: form.customer.email,
      customer_phone: form.customer.phone,
      participants_count: form.participantsCount,
      metadata: {
        swimming_level: form.swimmingLevel || undefined,
        participants: form.participants
          .map((p) => p.name.trim())
          .filter(Boolean),
        equipment_rental: form.equipmentRental,
        notes: form.notes || undefined,
        desired_date: form.desiredDate || undefined,
        desired_time: form.desiredTime || undefined,
        activity_source: 'surfing_app',
      },
      payment_type: DEFAULT_PAYMENT_TYPE,
    }

    try {
      const response: AxiosResponse<
        ReservationPayload | { success?: boolean; data?: ReservationPayload }
      > = await apiClient.post('/reservations', payload)

      const rawResult = response.data
      const normalized: ReservationPayload | undefined =
        rawResult && 'reservation' in rawResult
          ? rawResult as ReservationPayload
          : rawResult && 'data' in rawResult
            ? rawResult.data
            : undefined

      setSubmitStatus('success')
      setStep('success')
      setReservationRef(normalized?.reservation?.uuid ?? null)
    } catch (err) {
      setSubmitStatus('error')
      setSubmitError(
        err instanceof Error
          ? err.message
          : 'Impossible de confirmer la réservation',
      )
    }
  }

  if (isLoading && step === 'intro') {
    return (
      <div className="booking-shell">
        <Loader label="Chargement de l'activité surf..." />
      </div>
    )
  }

  if (status === 'error') {
    return (
      <div className="booking-shell">
        <Alert variant="danger" heading="Activité indisponible" description={error} />
      </div>
    )
  }

  const requiredFieldsValid =
    form.customer.firstName.trim() &&
    form.customer.lastName.trim() &&
    form.customer.email.trim() &&
    form.customer.phone.trim() &&
    (swimmingOptions.length === 0 || form.swimmingLevel)

  return (
    <div className="booking-shell">
      <section className="booking-card">
        <header className="booking-header">
          <div>
            <p className="booking-step">
              <span>{getBookingStepLabel(step)}</span>
            </p>
            <h2>{activity?.name ?? 'Cours de surf collectif'}</h2>
            <p>{activity?.description}</p>
          </div>
          <div className="booking-price">
            <strong>{formatCurrency(priceSummary.total)}</strong>
            <span>
              acompte {formatCurrency(priceSummary.deposit)} ·{' '}
              {form.participantsCount}{' '}
              {form.participantsCount > 1 ? 'participants' : 'participant'}
            </span>
          </div>
        </header>

        {step === 'intro' && (
          <div className="booking-intro">
            <Card
              heading="Ce qui est inclus"
              subheading="Séance 2h avec coach certifié, matériel disponible, briefing sécurité"
            >
              <ul className="booking-list">
                <li>Groupe de {minParticipants} à {maxParticipants} participants</li>
                <li>Niveau requis : savoir nager (sélectionne ton niveau)</li>
                <li>Spot sélectionné selon la houle du jour</li>
              </ul>
            </Card>

            <div className="booking-cta">
              <Button size="lg" onClick={() => setStep('details')}>
                {BOOKING_COPY.CTA_START}
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
              <FormField
                label="Participants"
                hint={`Entre ${minParticipants} et ${maxParticipants} personnes`}
              >
                <input
                  type="number"
                  min={minParticipants}
                  max={maxParticipants}
                  value={form.participantsCount}
                  onChange={(event) =>
                    handleParticipantsCountChange(
                      Number.parseInt(event.target.value, 10),
                    )
                  }
                />
              </FormField>

              {swimmingOptions.length > 0 && (
                <FormField label="Niveau de natation" required>
                  <select
                    value={form.swimmingLevel}
                    onChange={(event) =>
                      setForm((prev) => ({
                        ...prev,
                        swimmingLevel: event.target.value as SwimmingLevel,
                      }))
                    }
                  >
                    <option value="">Sélectionner</option>
                    {swimmingOptions.map((option) => (
                      <option key={option} value={option}>
                        {option === 'beginner'
                          ? 'Débutant'
                          : option === 'intermediate'
                            ? 'Intermédiaire'
                            : option === 'advanced'
                              ? 'Avancé'
                              : option}
                      </option>
                    ))}
                  </select>
                </FormField>
              )}
            </div>

            <div className="booking-participants">
              <h3>Participants</h3>
              <p>
                Partage les prénoms pour personnaliser l&rsquo;accueil (optionnel).
              </p>
              <div className="booking-participants-grid">
                {form.participants.slice(0, form.participantsCount).map((p, index) => (
                  <input
                    key={index}
                    type="text"
                    value={p.name}
                    placeholder={`Participant ${index + 1}`}
                    onChange={(event) =>
                      handleParticipantNameChange(index, event.target.value)
                    }
                  />
                ))}
              </div>
            </div>

            <div className="booking-form-grid">
              <FormField label="Date souhaitée">
                <input
                  type="date"
                  value={form.desiredDate}
                  onChange={(event) =>
                    setForm((prev) => ({
                      ...prev,
                      desiredDate: event.target.value,
                    }))
                  }
                />
              </FormField>
              <FormField label="Heure souhaitée">
                <input
                  type="time"
                  value={form.desiredTime}
                  onChange={(event) =>
                    setForm((prev) => ({
                      ...prev,
                      desiredTime: event.target.value,
                    }))
                  }
                />
              </FormField>
            </div>

            <div className="booking-options">
              <label>
                <input
                  type="checkbox"
                  checked={form.equipmentRental}
                  onChange={(event) =>
                    setForm((prev) => ({
                      ...prev,
                      equipmentRental: event.target.checked,
                    }))
                  }
                />
                <span>
                  Je souhaite inclure la location de combinaison et planche pour le
                  groupe.
                </span>
              </label>
            </div>

            <FormField label="Commentaire" hint="Allergies, taille combinaisons, etc.">
              <textarea
                value={form.notes}
                onChange={(event) =>
                  setForm((prev) => ({ ...prev, notes: event.target.value }))
                }
                placeholder="Ex: 2 combinaisons taille M, 1 taille L"
                rows={3}
              />
            </FormField>

            <div className="booking-actions">
              <Button
                variant="secondary"
                onClick={() => setStep('intro')}
              >
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
                  <dt>Détails séance</dt>
                  <dd>
                    {form.participantsCount}{' '}
                    {form.participantsCount > 1
                      ? 'participants'
                      : 'participant'}
                    <br />
                    Niveau :{' '}
                    {form.swimmingLevel
                      ? form.swimmingLevel
                      : 'non spécifié'}
                    <br />
                    {form.desiredDate && `Date souhaitée : ${form.desiredDate}`}
                    {form.desiredTime && <>, {form.desiredTime}</>}
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
              <Alert variant="danger" heading="Erreur" description={submitError} />
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
            <Card heading={BOOKING_COPY.SUCCESS_DEFAULT_HEADING}>
              <p>{BOOKING_COPY.SUCCESS_SECONDARY_HEADING}</p>
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

