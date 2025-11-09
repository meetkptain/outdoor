import { render, screen, waitFor } from '@testing-library/react'
import { describe, it, expect, beforeEach, vi } from 'vitest'
import { createMemoryRouter, Outlet, RouterProvider } from 'react-router-dom'
import ReservationsPage from '../routes/ReservationsPage'
import type { AdminOutletContext } from '../App'
import apiClient from '@parapente/shared/api/client'

vi.mock('@parapente/shared/api/client', async () => {
  const actual = await vi.importActual<typeof import('@parapente/shared/api/client')>(
    '@parapente/shared/api/client',
  )
  return {
    default: {
      ...actual.default,
      get: vi.fn(actual.default.get.bind(actual.default)),
    },
  }
})

const mockedGet = apiClient.get as unknown as ReturnType<typeof vi.fn>

function renderWithContext(context: AdminOutletContext) {
  const ContextLayout = () => <Outlet context={context} />

  const router = createMemoryRouter(
    [
      {
        path: '/',
        element: <ContextLayout />,
        children: [{ index: true, element: <ReservationsPage /> }],
      },
    ],
    {
      initialEntries: ['/'],
    },
  )

  return render(<RouterProvider router={router} />)
}

describe('ReservationsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('asks for authentication when no token is present', () => {
    const context: AdminOutletContext = {
      branding: null,
      fetchState: 'idle',
      tenantState: {
        organizationId: undefined,
        branding: undefined,
        user: null,
        featureFlags: {},
      },
      error: null,
      refreshTenantState: vi.fn(),
    }

    renderWithContext(context)

    expect(
      screen.getByText(
        'Connecte-toi pour consulter et gérer les réservations du tenant multi-niche.',
      ),
    ).toBeInTheDocument()
  })

  it('renders reservations table when data is available', async () => {
    mockedGet.mockResolvedValue({
      data: {
        data: {
          data: [
            {
              id: 1,
              uuid: 'abc1234567',
              status: 'scheduled',
              participants_count: 2,
              customer_first_name: 'Léa',
              customer_last_name: 'Vague',
              created_at: '2025-11-08T10:00:00Z',
              activity: { display_name: 'Surf collectif' },
            },
          ],
          pagination: {
            total: 1,
            per_page: 10,
            current_page: 1,
            last_page: 1,
          },
        },
      },
    })

    const context: AdminOutletContext = {
      branding: null,
      fetchState: 'ready',
      tenantState: {
        organizationId: '1',
        branding: undefined,
        user: null,
        featureFlags: {},
        token: 'demo-token',
      },
      error: null,
      refreshTenantState: vi.fn(),
    }

    renderWithContext(context)

    await waitFor(() => {
      expect(mockedGet).toHaveBeenCalledWith('/admin/reservations', {
        params: { per_page: 10 },
      })
    })

    expect(screen.getByText('Réservations')).toBeInTheDocument()
    expect(screen.getByText('Surf collectif')).toBeInTheDocument()
    expect(screen.getByText('Total 1')).toBeInTheDocument()
  })
})

