import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { RouterProvider } from 'react-router-dom'
import './index.css'
import router from './router.tsx'

if (import.meta.env.DEV) {
  import('@parapente/shared').then(({ initAxe }) => {
    initAxe?.()
  })
}

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <RouterProvider router={router} />
  </StrictMode>,
)
