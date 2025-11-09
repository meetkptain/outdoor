import { useEffect, useState } from 'react'
import { apiClient } from '@parapente/shared'
import type { AxiosResponse } from '@parapente/shared'
import { useOutletContext } from 'react-router-dom'
import type { AdminOutletContext } from '../App'
import './ResourcesPage.css'

type InstructorRecord = {
  id: number
  full_name?: string
  activity_types?: string[]
  email?: string
  phone?: string
  upcoming_sessions_count?: number
}

type SiteRecord = {
  id: number
  name?: string
  city?: string
  activity_types?: string[]
  is_active?: boolean
}

type InstructorsResponse =
  | {
      success?: boolean
      data?: {
        data?: InstructorRecord[]
      }
    }
  | {
      data?: InstructorRecord[]
    }

type SitesResponse =
  | {
      success?: boolean
      data?: {
        data?: SiteRecord[]
      }
    }
  | {
      data?: SiteRecord[]
    }

export default function ResourcesPage() {
  const { tenantState } = useOutletContext<AdminOutletContext>()
  const [instructors, setInstructors] = useState<InstructorRecord[]>([])
  const [sites, setSites] = useState<SiteRecord[]>([])
  const [status, setStatus] = useState<'idle' | 'loading' | 'ready' | 'error'>(
    'idle',
  )
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!tenantState.token) {
      setStatus('idle')
      setInstructors([])
      setSites([])
      return
    }

    setStatus('loading')
    Promise.all([
      apiClient.get<InstructorsResponse>('/admin/instructors'),
      apiClient.get<SitesResponse>('/admin/sites'),
    ])
      .then(
        ([
          instructorsResponse,
          sitesResponse,
        ]: [
          AxiosResponse<InstructorsResponse>,
          AxiosResponse<SitesResponse>,
        ]) => {
        const instructorsPayload = (
          'data' in instructorsResponse.data && instructorsResponse.data.data
            ? instructorsResponse.data.data
            : instructorsResponse.data
        ) as { data?: InstructorRecord[] } | InstructorRecord[] | undefined
        const sitesPayload = (
          'data' in sitesResponse.data && sitesResponse.data.data
            ? sitesResponse.data.data
            : sitesResponse.data
        ) as { data?: SiteRecord[] } | SiteRecord[] | undefined

        const resolvedInstructors = Array.isArray(instructorsPayload)
          ? instructorsPayload
          : instructorsPayload?.data ?? []
        const resolvedSites = Array.isArray(sitesPayload)
          ? sitesPayload
          : sitesPayload?.data ?? []

        setInstructors(resolvedInstructors)
        setSites(resolvedSites)
        setStatus('ready')
      })
      .catch((err: unknown) => {
        const message =
          err instanceof Error
            ? err.message
            : 'Impossible de récupérer les ressources'
        setError(message)
        setStatus('error')
      })
  }, [tenantState.token])

  if (!tenantState.token) {
    return (
      <section className="panel">
        <h2>Ressources</h2>
        <p>
          Connecte-toi pour visualiser les instructeurs, sites et équipements du
          tenant.
        </p>
      </section>
    )
  }

  return (
    <div className="resources-grid">
      <section className="panel">
        <header className="resources-header">
          <h2>Instructeurs</h2>
          <span className="badge badge-muted">
            {instructors.length.toLocaleString('fr-FR')}
          </span>
        </header>

        {status === 'loading' && <p>Chargement des instructeurs…</p>}
        {status === 'error' && (
          <div className="panel panel-inline-error">
            <h3>Erreur</h3>
            <p>{error}</p>
          </div>
        )}

        {status === 'ready' && instructors.length === 0 && (
          <p className="empty-state">
            Aucun instructeur n&apos;est configuré pour ce tenant. Ajoute-les
            depuis l&apos;API ou depuis l&apos;interface legacy.
          </p>
        )}

        {status === 'ready' && instructors.length > 0 && (
          <ul className="resource-list">
            {instructors.map((instructor) => (
              <li key={instructor.id} className="resource-item">
                <div>
                  <p className="resource-title">
                    {instructor.full_name ?? 'Instructeur sans nom'}
                  </p>
                  {instructor.email && (
                    <p className="resource-meta">{instructor.email}</p>
                  )}
                  {instructor.phone && (
                    <p className="resource-meta">{instructor.phone}</p>
                  )}
                </div>
                <div className="resource-tags">
                  {(instructor.activity_types ?? []).map((activity) => (
                    <span key={activity} className="tag">
                      {activity}
                    </span>
                  ))}
                  {typeof instructor.upcoming_sessions_count === 'number' && (
                    <span className="tag tag-outline">
                      Sessions à venir:{' '}
                      {instructor.upcoming_sessions_count.toLocaleString('fr-FR')}
                    </span>
                  )}
                </div>
              </li>
            ))}
          </ul>
        )}
      </section>

      <section className="panel">
        <header className="resources-header">
          <h2>Sites</h2>
          <span className="badge badge-muted">
            {sites.length.toLocaleString('fr-FR')}
          </span>
        </header>

        {status === 'loading' && <p>Chargement des sites…</p>}

        {status === 'ready' && sites.length === 0 && (
          <p className="empty-state">
            Aucun site configuré. Utilise `/admin/sites` pour en créer et les
            associer aux activités multi-niche.
          </p>
        )}

        {status === 'ready' && sites.length > 0 && (
          <ul className="resource-list">
            {sites.map((site) => (
              <li key={site.id} className="resource-item">
                <div>
                  <p className="resource-title">{site.name ?? 'Site sans nom'}</p>
                  <p className="resource-meta">
                    {site.city ?? 'Ville inconnue'} ·{' '}
                    {site.is_active ? 'Actif' : 'Inactif'}
                  </p>
                </div>
                <div className="resource-tags">
                  {(site.activity_types ?? []).map((activity) => (
                    <span key={activity} className="tag">
                      {activity}
                    </span>
                  ))}
                </div>
              </li>
            ))}
          </ul>
        )}
      </section>
    </div>
  )
}

