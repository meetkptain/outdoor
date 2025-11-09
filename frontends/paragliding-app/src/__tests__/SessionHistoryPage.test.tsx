import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryRouter, RouterProvider, Outlet } from 'react-router-dom'
import type { ReactNode } from 'react'
import { tenantStore } from '@parapente/shared'
import type { ParaglidingOutletContext } from '../App'
import SessionHistoryPage from '../routes/SessionHistoryPage'
import BookingPage from '../routes/BookingPage'
import apiClient from '@parapente/shared/api/client'

vi.mock('../hooks/useParaglidingActivity', () => {
  return {
    useParaglidingActivity: () => ({
      activity: {
        id: 5,
        name: 'Baptême parapente biplace',
        activity_type: 'paragliding',
        metadata: {
          default_takeoff_site: 'Col de la Forclaz',
        },
      },
      status: 'ready' as const,
      error: null,
    }),
  }
})

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
      <button {...props} disabled={props.disabled || isLoading}>
        {isLoading ? 'loading' : children}
      </button>
    ),
    Card: ({
      children,
      heading,
    }: {
      children: ReactNode
      heading?: ReactNode
    }) => (
      <section>
        {heading && <h3>{heading}</h3>}
        <div>{children}</div>
      </section>
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
    Loader: ({ label }: { label?: string }) => (
      <div role="status">{label ?? 'loading'}</div>
    ),
  }
})

vi.mock('@parapente/shared/api/client', async () => {
  const actual =
    await vi.importActual<typeof import('@parapente/shared/api/client')>(
      '@parapente/shared/api/client',
    )
  return {
    default: {
      ...actual.default,
      get: vi.fn(),
      post: vi.fn(),
    },
  }
})

const mockedGet = apiClient.get as unknown as ReturnType<typeof vi.fn>
const mockedPost = apiClient.post as unknown as ReturnType<typeof vi.fn>

function renderWithContext(context: ParaglidingOutletContext, initialPath = '/') {
  const Layout = () => <Outlet context={context} />

  const router = createMemoryRouter(
    [
      {
        path: '/',
        element: <Layout />,
        children: [
          { index: true, element: <BookingPage /> },
          { path: 'historique', element: <SessionHistoryPage /> },
        ],
      },
    ],
    { initialEntries: [initialPath] },
  )

  return render(<RouterProvider router={router} />)
}

describe('SessionHistoryPage', () => {
  beforeEach(() => {
    tenantStore.reset()
    tenantStore.setBranding({
      primaryColor: '#2563eb',
      secondaryColor: '#1f2937',
    })
    mockedGet.mockReset()
    mockedPost.mockReset()
  })

  it('renders upcoming sessions and upsell offers', async () => {
    mockedGet.mockResolvedValue({
      data: [
        {
          uuid: 'session-1',
          date: '2025-08-12T08:00:00Z',
          window: 'morning',
          pilot: 'Camille',
          status: 'scheduled',
          weight_kg: 82,
          shuttle_pickup: 'Gare de Talloires',
          observers: 1,
          upsell_offers: [
            {
              id: 'offer-1',
              name: 'Pack photo',
              price: 80,
              recommended: true,
              description: 'Photos + vidéo 4K',
            },
          ],
        },
      ],
    })

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
    }, '/historique')

    await screen.findByText(/Prochaine\(s\) session\(s\)/)
    expect(screen.getByText(/Camille/)).toBeInTheDocument()
    expect(screen.getByText(/Matin \(8h - 11h\)/)).toBeInTheDocument()
    expect(screen.getByText(/Pack photo/)).toBeInTheDocument()
  })

  it('allows accepting an upsell offer', async () => {
    mockedGet.mockResolvedValue({
      data: [
        {
          uuid: 'session-1',
          date: '2025-08-12T08:00:00Z',
          window: 'morning',
          pilot: 'Camille',
          status: 'upsell_pending',
          upsell_offers: [
            {
              id: 'offer-1',
              name: 'Pack photo',
              price: 80,
              description: 'Photos HD',
            },
          ],
        },
      ],
    })

    mockedPost.mockResolvedValue({
      data: { success: true },
    })

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
    }, '/historique')

    const addButton = await screen.findByRole('button', {
      name: /Ajouter au vol/i,
    })
    fireEvent.click(addButton)

    await waitFor(() => {
      expect(mockedPost).toHaveBeenCalledWith(
        '/paragliding/sessions/session-1/upsell',
        {
          offer_id: 'offer-1',
          action: 'accept',
        },
      )
    })

    expect(
      await screen.findByText(/Pack ajouté à ta réservation./i),
    ).toBeInTheDocument()
  })
})


