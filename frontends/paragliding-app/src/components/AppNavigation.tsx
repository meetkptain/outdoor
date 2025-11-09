import { NavLink } from 'react-router-dom'
import './AppNavigation.css'

export default function AppNavigation() {
  return (
    <nav className="paragliding-nav">
      <NavLink
        to="/"
        className={({ isActive }) =>
          `paragliding-nav-link ${isActive ? 'active' : ''}`
        }
        end
      >
        Réservation
      </NavLink>
      <NavLink
        to="/historique"
        className={({ isActive }) =>
          `paragliding-nav-link ${isActive ? 'active' : ''}`
        }
      >
        Historique & Upsell
      </NavLink>
    </nav>
  )
}


