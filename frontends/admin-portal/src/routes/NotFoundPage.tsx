import { Link } from 'react-router-dom'

export default function NotFoundPage() {
  return (
    <section className="panel">
      <h2>Page non trouvée</h2>
      <p>
        La ressource demandée n&apos;existe pas dans l&apos;administration multi-niche.
        Vérifie l&apos;URL ou reviens au tableau de bord.
      </p>
      <Link to="/" className="nav-cta">
        Retourner au tableau de bord
      </Link>
    </section>
  )
}

