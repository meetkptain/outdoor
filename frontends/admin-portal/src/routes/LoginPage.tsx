import { useState } from 'react'
import type { FormEvent } from 'react'
import { useNavigate, useOutletContext } from 'react-router-dom'
import { apiClient, tenantStore } from '@parapente/shared'
import type { AdminOutletContext } from '../App'
import type { AxiosResponse } from '@parapente/shared'
import './LoginPage.css'

type LoginPayload = {
  token?: string
  user?: {
    id: number
    name: string
    email: string
    role: string
  }
  organization?: {
    id: number | string
  }
}

type LoginResponse =
  | {
      success?: boolean
      data?: LoginPayload
      message?: string
    }
  | LoginPayload

export default function LoginPage() {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [status, setStatus] = useState<'idle' | 'loading' | 'error' | 'success'>(
    'idle',
  )
  const [error, setError] = useState<string | null>(null)
  const navigate = useNavigate()
  const { refreshTenantState } = useOutletContext<AdminOutletContext>()

  const onSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setStatus('loading')
    setError(null)

    apiClient
      .post<LoginResponse>('/auth/login', {
        email,
        password,
      })
      .then((response: AxiosResponse<LoginResponse>) => {
        const payload = (
          'data' in response.data && response.data.data
            ? response.data.data
            : response.data
        ) as LoginPayload | undefined

        const token = payload?.token
        if (token) {
          tenantStore.setToken(token)
        }
        if (payload?.user) {
          tenantStore.setUser({
            id: payload.user.id,
            name: payload.user.name,
            email: payload.user.email,
            role: payload.user.role,
          })
        }
        if (payload?.organization?.id) {
          tenantStore.setOrganization(String(payload.organization.id))
        }

        refreshTenantState()
        setStatus('success')
        navigate('/')
      })
      .catch((err: unknown) => {
        const message =
          err instanceof Error
            ? err.message
            : 'Connexion impossible. Vérifie tes identifiants.'
        setError(message)
        setStatus('error')
      })
  }

  return (
    <section className="panel login-panel">
      <h2>Connexion administrateur</h2>
      <p className="login-subtitle">
        Identifie-toi pour administrer les activités multi-niche du tenant.
      </p>
      <form className="login-form" onSubmit={onSubmit}>
        <label className="login-field">
          <span>Email</span>
          <input
            type="email"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            required
            placeholder="admin@tenant.com"
            autoComplete="email"
          />
        </label>

        <label className="login-field">
          <span>Mot de passe</span>
          <input
            type="password"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            required
            placeholder="********"
            autoComplete="current-password"
          />
        </label>

        {error && <p className="login-error">{error}</p>}

        <button
          type="submit"
          className="nav-cta"
          disabled={status === 'loading'}
        >
          {status === 'loading' ? 'Connexion…' : 'Se connecter'}
        </button>
      </form>

      {status === 'success' && (
        <p className="login-success">
          Connexion réussie, redirection vers le tableau de bord…
        </p>
      )}
    </section>
  )
}

