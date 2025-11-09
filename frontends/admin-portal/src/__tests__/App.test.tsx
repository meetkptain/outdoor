import { render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { describe, it, expect, beforeEach, vi } from 'vitest'
import App from '../App'
import LoginPage from '../routes/LoginPage'
import DashboardPage from '../routes/DashboardPage'
import { tenantStore } from '@parapente/shared'
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

describe('App routing', () => {
  beforeEach(() => {
    tenantStore.reset()
    window.localStorage.clear()
    vi.clearAllMocks()
  })

  it('renders login layout when visiting /login', () => {
    render(
      <MemoryRouter initialEntries={['/login']}>
        <Routes>
          <Route path="/" element={<App />}>
            <Route path="login" element={<LoginPage />} />
          </Route>
        </Routes>
      </MemoryRouter>,
    )

    expect(
      screen.getByRole('heading', { name: 'Parapente SaaS Admin' }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('heading', { name: 'Connexion administrateur' }),
    ).toBeInTheDocument()
  })

  it('renders dashboard layout for authenticated users', async () => {
    mockedGet.mockResolvedValue({
      data: { data: { primaryColor: '#123456', secondaryColor: '#1f2937' } },
    })

    tenantStore.setToken('demo-token')
    tenantStore.setOrganization('99')

    render(
      <MemoryRouter initialEntries={['/']}>
        <Routes>
          <Route path="/" element={<App />}>
            <Route index element={<DashboardPage />} />
            <Route path="login" element={<LoginPage />} />
          </Route>
        </Routes>
      </MemoryRouter>,
    )

    await waitFor(() => {
      expect(mockedGet).toHaveBeenCalledWith('/branding')
    })

    expect(screen.getByText('Tableau de bord')).toBeInTheDocument()
    expect(screen.queryByText('Authentification requise')).not.toBeInTheDocument()
  })
})

