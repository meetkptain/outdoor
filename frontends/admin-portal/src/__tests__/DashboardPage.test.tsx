import { render, screen, waitFor } from '@testing-library/react'
import { describe, it, expect, beforeEach, vi } from 'vitest'
import { createMemoryRouter, Outlet, RouterProvider } from 'react-router-dom'
import DashboardPage from '../routes/DashboardPage'
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
        children: [{ index: true, element: <DashboardPage /> }],
      },
    ],
    { initialEntries: ['/'] },
  )

  return render(<RouterProvider router={router} />)
}

describe('DashboardPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('requests authentication when token missing', () => {
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
        'Connecte-toi pour visualiser les indicateurs multi-niche du tenant.',
      ),
    ).toBeInTheDocument()
  })

  it('displays metrics when API responds', async () => {
    mockedGet.mockResolvedValue({
      data: { data: { reservations_today: 3, instructors_active: 2 } },
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
      expect(mockedGet).toHaveBeenCalledWith('/admin/dashboard/summary')
    })

    expect(screen.getByText('Indicateurs clés')).toBeInTheDocument()
    expect(screen.getByText('reservations_today')).toBeInTheDocument()
    expect(screen.getByText('3')).toBeInTheDocument()
  })
})

