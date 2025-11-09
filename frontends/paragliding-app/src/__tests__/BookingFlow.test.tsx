import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { describe, it, expect, beforeEach, vi } from 'vitest'
import type { ReactNode } from 'react'
import { createMemoryRouter, Outlet, RouterProvider } from 'react-router-dom'
import BookingPage from '../routes/BookingPage'
import type { ParaglidingOutletContext } from '../App'
import { tenantStore } from '@parapente/shared'
import apiClient from '@parapente/shared/api/client'

vi.mock('@parapente/shared', async () => {
  const actual =
    await vi.importActual<typeof import('@parapente/shared')>(
      '@parapente/shared',
    )
  return {
    ...actual,
    Button: ({
      children,
      isLoading,
      ...props
    }: React.ButtonHTMLAttributes<HTMLButtonElement> & {
      isLoading?: boolean
    }) => (
      <button {...props} disabled={isLoading || props.disabled}>
        {isLoading ? 'loading' : children}
      </button>
    ),
    Card: ({
      children,
      heading,
      subheading,
    }: {
      children: ReactNode
      heading?: ReactNode
      subheading?: ReactNode
    }) => (
      <section>
        {heading && <h3>{heading}</h3>}
        {subheading && <p>{subheading}</p>}
        <div>{children}</div>
      </section>
    ),
    FormField: ({
      label,
      children,
      hint,
    }: {
      label: ReactNode
      children: ReactNode
      hint?: ReactNode
    }) => (
      <label>
        <span>{label}</span>
        {children}
        {hint}
      </label>
    ),
    Alert: ({
      heading,
      description,
    }: {
      heading?: ReactNode
      description?: ReactNode
    }) => (
      <div role="alert">
        {heading}
        {description}
      </div>
    ),
    Loader: () => <div role="status">loading</div>,
  }
})

const mockActivity = {
  id: 5,
  organization_id: 1,
  activity_type: 'paragliding',
  name: 'Baptême parapente biplace',
  description: 'Décollage assisté, briefing sécurité et vol panoramique de 25 minutes.',
  duration_minutes: 25,
  pricing_config: {
    model: 'fixed',
    base_price: 160,
    deposit_amount: 60,
  },
  constraints_config: {
    participants: {
      min: 1,
      max: 1,
    },
  },
  metadata: {
    session_strategy: 'scheduled',
    flight_windows: ['morning', 'afternoon', 'sunset'],
    max_total_weight: 120,
    recommended_weight: 95,
    max_observers: 2,
    pilot_roster: ['Camille', 'Loïc'],
    default_takeoff_site: 'Col de la Forclaz',
  },
}

const mockActivityResult = {
  activity: mockActivity,
  status: 'ready' as const,
  error: null,
}

vi.mock('../hooks/useParaglidingActivity', () => {
  return {
    useParaglidingActivity: () => mockActivityResult,
  }
})

vi.mock('@parapente/shared/api/client', async () => {
  const actual = await vi.importActual<typeof import('@parapente/shared/api/client')>(
    '@parapente/shared/api/client',
  )
  return {
    default: {
      ...actual.default,
      post: vi.fn(),
      get: vi.fn(),
    },
  }
})

const mockedPost = apiClient.post as unknown as ReturnType<typeof vi.fn>
const mockedGet = apiClient.get as unknown as ReturnType<typeof vi.fn>

function renderWithContext(context: ParaglidingOutletContext) {
  const Layout = () => <Outlet context={context} />

  const router = createMemoryRouter(
    [
      {
        path: '/',
        element: <Layout />,
        children: [{ index: true, element: <BookingPage /> }],
      },
    ],
    { initialEntries: ['/'] },
  )

  return render(<RouterProvider router={router} />)
}

describe('Paragliding booking flow', () => {
  beforeEach(() => {
    tenantStore.reset()
    tenantStore.setBranding({
      primaryColor: '#2563eb',
      secondaryColor: '#1f2937',
    })
    mockedPost.mockResolvedValue({
      data: {
        reservation: { uuid: 'para-123', status: 'pending' },
      },
    })
    mockedGet.mockResolvedValue({
      data: {
        available: true,
        next_slot: '08h30',
      },
    })
  })

  it('allows user to complete booking flow and submits correct payload', async () => {
    renderWithContext({
      branding: tenantStore.getState().branding ?? null,
      palette: {
        primary: '#2563eb',
        primaryForeground: '#ffffff',
        secondary: '#1f2937',
        secondaryForeground: '#ffffff',
        neutral: '#6b7280',
        surface: '#111827',
        surfaceBorder: 'rgba(148, 163, 184, 0.2)',
        success: '#16a34a',
        warning: '#d97706',
        danger: '#dc2626',
        info: '#0ea5e9',
      },
      status: 'ready',
    })

    fireEvent.click(
      screen.getByRole('button', { name: 'Préparer mon vol' }),
    )

    fireEvent.change(screen.getByLabelText(/^Prénom$/i), {
      target: { value: 'Léa' },
    })
    fireEvent.change(screen.getByLabelText(/^Nom$/i), {
      target: { value: 'Vague' },
    })
    fireEvent.change(screen.getByLabelText('Email'), {
      target: { value: 'lea@example.com' },
    })
    fireEvent.change(screen.getByLabelText(/^Téléphone$/i), {
      target: { value: '+33600000000' },
    })
    fireEvent.change(screen.getByLabelText(/Date de vol souhaitée/i), {
      target: { value: '2025-07-12' },
    })
    fireEvent.change(screen.getByLabelText(/Créneau de vol/i), {
      target: { value: 'morning' },
    })
    fireEvent.change(screen.getByLabelText(/Poids passager/i), {
      target: { value: '82' },
    })

    fireEvent.click(
      screen.getByLabelText(/Inclure le pack photo \/ vidéo/i),
    )

    fireEvent.click(
      screen.getByLabelText(/Navette décollage nécessaire/i),
    )

    fireEvent.change(screen.getByLabelText(/Lieu de prise en charge/i), {
      target: { value: 'Gare de Talloires' },
    })
    fireEvent.change(screen.getByLabelText(/Accompagnateurs/i), {
      target: { value: '1' },
    })

    await waitFor(() => {
      expect(mockedGet).toHaveBeenCalledWith(
        '/paragliding/shuttles/availability',
        {
          params: {
            date: '2025-07-12',
            window: 'morning',
            seats: 2,
          },
        },
      )
    })

    await screen.findByText(/Navette disponible/)

    fireEvent.change(screen.getByLabelText(/Notes/i), {
      target: { value: 'Préférence décollage tôt le matin, sans vertige.' },
    })

    fireEvent.click(screen.getByRole('button', { name: 'Continuer' }))

    fireEvent.click(
      screen.getByRole('button', { name: 'Confirmer la réservation' }),
    )

    await waitFor(() => {
      expect(mockedPost).toHaveBeenCalledTimes(1)
    })

    const payload = mockedPost.mock.calls[0][1]
    expect(payload.activity_id).toBe(5)
    expect(payload.customer_first_name).toBe('Léa')
    expect(payload.participants_count).toBe(1)
    expect(payload.metadata.flight_date).toBe('2025-07-12')
    expect(payload.metadata.flight_window).toBe('morning')
    expect(payload.metadata.passenger_weight_kg).toBe(82)
    expect(payload.metadata.needs_shuttle).toBe(true)
    expect(payload.metadata.pickup_location).toBe('Gare de Talloires')
    expect(payload.metadata.observer_count).toBe(1)
    expect(payload.metadata.wants_photos).toBe(true)
    expect(payload.metadata.shuttle_status).toBe('available')

    await waitFor(() => {
      expect(
        screen.getByText('Merci, ton vol est enregistré !'),
      ).toBeInTheDocument()
    })
  })
})

