# Jeu de données multi-niche (QA)

## Objectif
Fournir un socle de données cohérent pour tester les frontends SaaS multi-niche :

- 1 organisation démonstration multi-tenant avec branding.
- Parcours **parapente** (activité, site, instructeur, réservation + session).
- Parcours **surf** (activité, site, instructeur, réservation + session).
- Ressources associées (équipements) pour illustrer les workflows.

## Seeder dédié
Le seeder `MultiNicheDemoSeeder` crée l’ensemble du jeu de données.

```
php artisan migrate:fresh
php artisan db:seed --class=MultiNicheDemoSeeder
```

### Données générées (extrait)
- Organisation : `Demo Multi-Niche Adventures` (slug `demo-multi-niche`)
- Admin : `admin+demo@parapente.test` / `password`
- Activités :
  - Parapente `Baptême de l’air tandem` (pricing per_participant, option pack média)
  - Surf `Cours de surf collectif` (pricing tiered, contraintes swimming_level)
- Instructeurs :
  - Parapente : Alice Pilot (`pilot+paragliding@parapente.test` / `password`)
  - Surf : Bruno Coach (`coach+surf@parapente.test` / `password`)
- Réservations & sessions :
  - Vol parapente planifié (Camille Montagne)
  - Session surf planifiée (groupe de 4 participants)

## Utilisation côté frontend
- Configurer `VITE_DEFAULT_ORGANIZATION_ID=1` (ou l’id retourné par le seeder) dans `.env`.
- Se connecter via `admin+demo@parapente.test` / `password`.
- Les écrans Dashboard / Réservations / Ressources disposent automatiquement du contenu.

## Personnalisation
- Modifier les descriptions/prix directement dans le seeder ou via l’UI admin backend.
- Ajouter des activités supplémentaires (plongée, kitesurf…) en copiant le pattern du seeder.
- Pour réinitialiser : `php artisan migrate:fresh --seed --class=MultiNicheDemoSeeder`.

