# Phases de Mise en Œuvre Frontend

## Phase 0 – Préparation
- [x] Installer pnpm/node versions supportées.
- [x] Initialiser monorepo (workspaces) + dépendances partagées.
- [x] Configurer .env.front avec URL API, clé tenant par défaut (`frontends/.env.front.example`).
- [x] Générer seeds multi-niche (parapente + surf) pour QA (`MultiNicheDemoSeeder`).

## Phase 1 – Socle partagé (frontends/shared)
- [x] Créer API client (axios/fetch) avec intercepteurs Authorization, X-Organization-ID.
- [x] Mettre en place store global (tenant, branding, user) + gestion feature flags.
- [x] UI Kit : boutons, formulaires, layout, theming dynamique (branding resolver).
- [x] Auth hooks : useLogin, useLogout, useCurrentUser.
- [x] Tests unitaires (vitest + React Testing Library / équivalent).

## Phase 2 – Admin Portal
- [x] Dashboard summary (stats multi-activité, top instructors).
- [x] CRUD réservations (liste paginée, filtres, détail, assignation, options, completion).
- [x] Gestion ressources (sites, instructeurs, véhicules) et branding.
- [x] Tests : unités + E2E (Playwright) sur parcours admin.

## Phase 3 – Surfing App
- [x] Parcours instant booking (formulaire dynamique via constraints_config).
- [x] Ajout d’options before_flight, gestion équipements loués.
- [x] Vue planning instructeur (sessions + météo/marées).
- [x] Tests UI & E2E (booking complet + assignation auto).

## Phase 4 – Paragliding App
- [x] Parcours classique (réservation puis assignation).
- [x] Gestion navettes/poids (intégration VehicleService & options).
- [x] Historique sessions + upsell options.
- [x] Tests E2E : création -> assignation -> completion.

## Phase 5 – Consolidation & QA
- Factorisation libs modules (copywriting, i18n, stages workflow).
- Tests cross-tenant (branding différents) + accessibility (axe).
- Audit Lighthouse (perf/mobile/PWA).
- Revue manuelle multi-navigateur.

## Phase 6 – Release & Monitoring
- Documentation utilisateur (guides front) dans frontends/docs/.
- Pipelines CI/CD (lint/test/build/deploy) par app.
- Configurer Sentry/LogRocket (tenant + activité dans contexte).
- Checklists déploiement, rollback.

