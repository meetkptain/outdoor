import { useCallback, useState } from "react";
import apiClient from "../api/client";
import { tenantStore } from "../store/tenant";
import type { AuthLoginResponse, StandardResponse } from "./types";

type LoginStatus = "idle" | "loading" | "success" | "error";

interface LoginCredentials {
  email: string;
  password: string;
}

function normalizeAuthPayload(
  payload?: AuthLoginResponse | StandardResponse<AuthLoginResponse>,
): AuthLoginResponse | undefined {
  if (!payload) {
    return undefined;
  }
  if ("data" in payload) {
    return payload.data;
  }
  return payload as AuthLoginResponse;
}

export function useLogin() {
  const [status, setStatus] = useState<LoginStatus>("idle");
  const [error, setError] = useState<string | null>(null);

  const login = useCallback(async ({ email, password }: LoginCredentials) => {
    setStatus("loading");
    setError(null);

    try {
      const response = await apiClient.post<StandardResponse<AuthLoginResponse> | AuthLoginResponse>(
        "/auth/login",
        { email, password }
      );

      const raw =
        "data" in response.data && response.data.data !== undefined
          ? response.data.data
          : response.data;

      const payload = normalizeAuthPayload(raw);

      if (payload?.token) {
        tenantStore.setToken(payload.token);
      }
      if (payload?.user) {
        tenantStore.setUser(payload.user);
      }
      if (payload?.organization?.id) {
        tenantStore.setOrganization(String(payload.organization.id));
      }
      if (payload?.branding) {
        tenantStore.setBranding(payload.branding);
      }
      if (payload?.feature_flags) {
        Object.entries(payload.feature_flags).forEach(([key, value]) => {
          tenantStore.setFeatureFlag(key, Boolean(value));
        });
      }

      setStatus("success");
      return payload;
    } catch (err) {
      setStatus("error");
      setError(
        err instanceof Error ? err.message : "Connexion impossible. Vérifiez vos identifiants."
      );
      throw err;
    }
  }, []);

  return {
    login,
    status,
    error,
    isLoading: status === "loading",
    isSuccess: status === "success",
    isError: status === "error",
  };
}

