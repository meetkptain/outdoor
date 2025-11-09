import { useEffect, useState } from 'react'
import apiClient from '@parapente/shared/api/client'
import type { AxiosResponse } from '@parapente/shared'
import type { Activity } from '../types/activity'
import { SURF_ACTIVITY_ID } from '../config/env'

type ActivityResponse =
  | {
      success?: boolean
      data?: Activity
    }
  | Activity

export function useSurfActivity() {
  const [activity, setActivity] = useState<Activity | null>(null)
  const [status, setStatus] = useState<'idle' | 'loading' | 'ready' | 'error'>(
    'idle',
  )
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    let mounted = true

    async function fetchActivity() {
      setStatus('loading')
      setError(null)
      try {
        const response: AxiosResponse<ActivityResponse> = await apiClient.get(
          `/activities/${SURF_ACTIVITY_ID}`,
        )

        const payload =
          'data' in response.data && response.data.data
            ? response.data.data
            : response.data

        if (!mounted) return

        const nextActivity =
          payload && typeof payload === 'object'
            ? (payload as Activity)
            : null

        if (nextActivity) {
          setActivity(nextActivity)
          setStatus('ready')
        } else {
          setStatus('error')
          setError('Activité introuvable')
        }
      } catch (err) {
        if (!mounted) return
        setStatus('error')
        setError(
          err instanceof Error
            ? err.message
            : "Impossible de récupérer l'activité de surf",
        )
      }
    }

    fetchActivity()

    return () => {
      mounted = false
    }
  }, [])

  return {
    activity,
    status,
    error,
  }
}

