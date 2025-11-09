import { useSyncExternalStore } from "react";
import { tenantStore } from "../store/tenant";
import type { TenantState } from "../store/tenant";

export function useTenantState(): TenantState {
  return useSyncExternalStore(
    tenantStore.subscribe,
    tenantStore.getState,
    tenantStore.getState
  );
}

