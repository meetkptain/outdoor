# Shared Frontend Module (`@parapente/shared`)

## Contenu principal

- **API Client** : axios configuré `/api/v1`, intercepteurs `Authorization` + `X-Organization-ID`.
- **Store tenant (`tenantStore`)** :
  - état : `organizationId`, `branding`, `user`, `token`, `featureFlags`.
  - méthodes : `setOrganization`, `setBranding`, `setUser`, `setToken`, `setFeatureFlag`, `reset`, `subscribe`.
- **Hooks** :
  - `useTenantState` : sélectionne l’état via `useSyncExternalStore`.
  - `useLogin` / `useLogout` / `useCurrentUser` / `useRequireAuth`.
- **UI Kit** :
  - `Button`, `Card`, `Alert`, `FormField`, `Loader`.
  - theming dynamique via `buildPalette` / `getCurrentPalette`.

## Utilisation

```tsx
import {
  apiClient,
  tenantStore,
  useLogin,
  useTenantState,
  Button,
  Card,
} from "@parapente/shared";

const { login } = useLogin();
const tenant = useTenantState();
```

## Tests

Lancer depuis la racine :

```
pnpm --filter @parapente/shared test
```

Les suites couvrent store, hooks auth et UI.

