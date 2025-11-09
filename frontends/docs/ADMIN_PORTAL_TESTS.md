## Tests Admin Portal

### Unitaires (Vitest)
```
pnpm --filter admin-portal test
```
- couvre `App`, `DashboardPage`, `ReservationsPage`
- mocks axios (`apiClient`) et utilise le `tenantStore` partagé

### E2E (Playwright)
```
pnpm --filter admin-portal build
pnpm dlx playwright install --with-deps   # première exécution
pnpm --filter admin-portal test:e2e
```
- la config (`playwright.config.ts`) lance `pnpm preview` et intercepte les appels API (`/branding`, `/auth/login`, `/admin/...`) pour renvoyer des fixtures
- le scénario `tests/e2e/admin-flow.spec.ts` couvre login + liste des réservations

