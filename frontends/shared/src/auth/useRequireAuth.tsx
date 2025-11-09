import { useEffect } from "react";
import { useTenantState } from "../hooks/useTenantState";

interface UseRequireAuthOptions {
  onUnauthenticated?: () => void;
}

export function useRequireAuth({ onUnauthenticated }: UseRequireAuthOptions = {}) {
  const tenant = useTenantState();

  useEffect(() => {
    if (!tenant.token && onUnauthenticated) {
      onUnauthenticated();
    }
  }, [tenant.token, onUnauthenticated]);

  return tenant.token;
}

