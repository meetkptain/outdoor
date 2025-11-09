import { useTenantState } from "../hooks/useTenantState";

export function useCurrentUser() {
  const tenant = useTenantState();
  return tenant.user;
}

