import { createBrowserRouter } from 'react-router-dom'
import App from './App'
import BookingPage from './routes/BookingPage'
import SessionHistoryPage from './routes/SessionHistoryPage'

const router = createBrowserRouter([
  {
    path: '/',
    element: <App />,
    children: [
      { index: true, element: <BookingPage /> },
      { path: 'historique', element: <SessionHistoryPage /> },
    ],
  },
])

export default router

