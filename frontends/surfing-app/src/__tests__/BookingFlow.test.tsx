import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { describe, it, expect, beforeEach, vi } from 'vitest'
import type { ReactNode } from 'react'
import { createMemoryRouter, Outlet, RouterProvider } from 'react-router-dom'
import BookingPage from '../routes/BookingPage'
import type { SurfOutletContext } from '../App'
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
  id: 2,
  organization_id: 1,
  activity_type: 'surfing',
  name: 'Cours de surf collectif',
  description: 'Session de 2h encadrée par un coach diplômé.',
  pricing_config: {
    model: 'tiered',
    tiers: [
      { up_to: 3, price: 60 },
      { up_to: 6, price: 55 },
    ],
    deposit_amount: 20,
  },
  constraints_config: {
    participants: {
      min: 2,
      max: 6,
    },
    enums: {
      swimming_level: ['beginner', 'intermediate', 'advanced'],
    },
  },
  metadata: {
    session_strategy: 'per_reservation',
  },
}

const mockSurfActivityResult = {
  activity: mockActivity,
  status: 'ready' as const,
  error: null,
}

vi.mock('../hooks/useSurfActivity', () => {
  return {
    useSurfActivity: () => mockSurfActivityResult,
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
    },
  }
})

const mockedPost = apiClient.post as unknown as ReturnType<typeof vi.fn>

function renderWithContext(context: SurfOutletContext) {
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

describe('Surfing booking flow', () => {
  beforeEach(() => {
    tenantStore.reset()
    tenantStore.setBranding({
      primaryColor: '#2563eb',
      secondaryColor: '#1f2937',
    })
    mockedPost.mockResolvedValue({
      data: {
        reservation: { uuid: 'surf-123', status: 'pending' },
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
      screen.getByRole('button', { name: 'Commencer la réservation' }),
    )

    fireEvent.change(screen.getByLabelText(/Prénom/i), {
      target: { value: 'Léa' },
    })
    fireEvent.change(screen.getByLabelText(/^Nom$/i), {
      target: { value: 'Vague' },
    })
    fireEvent.change(screen.getByLabelText('Email'), {
      target: { value: 'lea@example.com' },
    })
    fireEvent.change(screen.getByLabelText('Téléphone'), {
      target: { value: '+33600000000' },
    })
    fireEvent.change(screen.getByLabelText(/Niveau de natation/i), {
      target: { value: 'intermediate' },
    })

    fireEvent.click(screen.getByRole('button', { name: 'Continuer' }))

    fireEvent.click(
      screen.getByRole('button', { name: 'Confirmer la réservation' }),
    )

    await waitFor(() => {
      expect(mockedPost).toHaveBeenCalledTimes(1)
    })

    const payload = mockedPost.mock.calls[0][1]
    expect(payload.activity_id).toBe(2)
    expect(payload.customer_first_name).toBe('Léa')
    expect(payload.participants_count).toBeGreaterThan(0)

    await waitFor(() => {
      expect(
        screen.getByText('Merci, ta demande est enregistrée !'),
      ).toBeInTheDocument()
    })
  })
})

