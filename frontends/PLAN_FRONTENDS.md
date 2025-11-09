# Plan Frontends SaaS Multi-niche

## 1. Objectifs
- Offrir des interfaces spécialisées par activité (paragliding, surfing, futures niches) en se basant sur l'API générique.
- Mutualiser auth, thème, logique multi-tenant et composants partagés.
- Permettre des déploiements indépendants (SPA/SSR) tout en conservant un socle commun.

## 2. Architecture Dossier
`
frontends/
├── shared/                 # libs UI, hooks API, auth, theming multitenant
├── admin-portal/           # dashboard gestion (stats, réservations, branding)
├── surfing-app/            # UX spécifique surf (workflow instant booking, équipements)
├── paragliding-app/        # UX parapente (workflow historique, navette, etc.)
└── docs/                   # guides, checklists front, conventions UI
`

## 3. Modules communs (frontends/shared)
- **API client** : wrappers axios/fetch vers /api/v1/... avec injection X-Organization-ID.
- **State store** : gestion tenant/branding/feature flags, cache (React Query / Zustand / Pinia selon stack).
- **UI kit** : composants atoms (Boutons, Modals), layout multi-tenant, i18n, theming (CSS variables dérivées du branding).
- **Auth** : hooks login/logout/me, redirection selon rôle (admin, instructor, client).
- **Utils** : formatage dates (sessions), prix (pricing_config), mapping stages workflow modules.

## 4. Applications dédiées
### 4.1 Admin Portal
- Tableaux de bord (stats multi-activité, top instructors, KPI sessions).
- Gestion réservations (pipeline multi-stage, assignations, options).
- Pages ressources (sites, équipements, instructeurs) en se basant sur nouvelles routes paginées.
- Branding & configuration tenant (UI preview du branding_resolver).

### 4.2 Surfing App
- Parcours instant booking : lecture contraintes (equired_metadata.swimming_level).
- Planning instructeur surf (sessions modules, météo/marées).
- Intégration equipment rental (options stage efore_flight).

### 4.3 Paragliding App
- Parcours classique (réservation + assignation différée).
- Gestion navettes / poids (VehicleService).
- Historique sessions, upsell options photos/vidéos.

### 4.4 Futurs modules
- Pattern clonable : reprenant app template + configuration ctivity_type, features, workflows.

## 5. Stratégie Technique
- **Stack** : privilégier monorepo front (pnpm workspace) → builds indépendants.
- **CI/CD** : pipeline lint/test/build par app, déploiement sur S3/CloudFront ou Vercel selon besoin.
- **Thematisation** : hydratation initiale via branding API, fallback defaults (mode SaaS).
- **Routing** : prévoir prise en charge sous-domaines tenant (	enant.front.example.com).

## 6. Roadmap Front
1. Mettre en place rontends/shared (API client, auth, thème).
2. Bootstraper dmin-portal (tableau de bord + gestion réservations).
3. Implémenter surfing-app avec flux complet (création → options → suivi).
4. Adapter paragliding-app (réservation traditionnelle, assignation).
5. Factoriser libs modules (stage workflows, copywriting, i18n).
6. Préparer playbook QA/UI (tests Cypress/Playwright, axe Lighthouse).

## 7. Tests & Qualité
- Tests unitaires front (vitest/jest) + tests e2e (Playwright/Cypress) par app.
- Contrôle contractuel API → utiliser Mock Service Worker ou pact.
- Vérifier compatibilité multi-tenant : tests snapshots sur plusieurs branding/setups.

## 8. Documentation
- Conventions de commit front, guidelines UI.
- Guide intégration API (mapping endpoints -> composants).
- Checklist déploiement (env vars, tenant defaults, feature flags).

