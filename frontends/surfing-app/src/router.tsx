import { createBrowserRouter } from 'react-router-dom'
import App from './App'
import BookingPage from './routes/BookingPage'

const router = createBrowserRouter([
  {
    path: '/',
    element: <App />,
    children: [
      {
        index: true,
        element: <BookingPage />,
      },
    ],
  },
])

export default router

