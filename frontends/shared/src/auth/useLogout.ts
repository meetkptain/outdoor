import { useCallback, useState } from "react";
import apiClient from "../api/client";
import { tenantStore } from "../store/tenant";

type LogoutStatus = "idle" | "loading" | "success" | "error";

export function useLogout() {
  const [status, setStatus] = useState<LogoutStatus>("idle");
  const [error, setError] = useState<string | null>(null);

  const logout = useCallback(async () => {
    setStatus("loading");
    setError(null);
    try {
      await apiClient.post("/auth/logout").catch(() => {
        // tolerates network errors and continues clearing local state
      });
      tenantStore.reset();
      setStatus("success");
    } catch (err) {
      setStatus("error");
      setError(err instanceof Error ? err.message : "Déconnexion impossible");
      throw err;
    } finally {
      tenantStore.reset();
    }
  }, []);

  return {
    logout,
    status,
    error,
    isLoading: status === "loading",
    isSuccess: status === "success",
    isError: status === "error",
  };
}

