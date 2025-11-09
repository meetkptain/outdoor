import { createBrowserRouter } from 'react-router-dom'
import App from './App'
import DashboardPage from './routes/DashboardPage'
import ReservationsPage from './routes/ReservationsPage'
import ResourcesPage from './routes/ResourcesPage'
import NotFoundPage from './routes/NotFoundPage'
import LoginPage from './routes/LoginPage'

const router = createBrowserRouter([
  {
    path: '/',
    element: <App />,
    children: [
      {
        index: true,
        element: <DashboardPage />,
      },
      {
        path: 'login',
        element: <LoginPage />,
      },
      {
        path: 'reservations',
        element: <ReservationsPage />,
      },
      {
        path: 'resources',
        element: <ResourcesPage />,
      },
      {
        path: '*',
        element: <NotFoundPage />,
      },
    ],
  },
])

export default router

