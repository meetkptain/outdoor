export interface TenantBranding {
  primaryColor?: string;
  secondaryColor?: string;
  logoUrl?: string;
  [key: string]: unknown;
}

export interface TenantState {
  organizationId?: string;
  branding?: TenantBranding;
  user?: {
    id: number;
    name: string;
    email: string;
    role: string;
  } | null;
  featureFlags: Record<string, boolean>;
  token?: string;
}

const state: TenantState = {
  featureFlags: {},
};

const listeners = new Set<() => void>();

function notifyListeners() {
  listeners.forEach((listener) => {
    try {
      listener();
    } catch (error) {
      // eslint-disable-next-line no-console
      console.error('tenantStore listener error', error);
    }
  });
}

export const tenantStore = {
  getState: (): TenantState => state,
  hydrateFromStorage() {
    if (typeof window === "undefined") {
      return;
    }
    state.organizationId =
      window.localStorage.getItem("organization_id") ?? state.organizationId;
    const storedToken = window.localStorage.getItem("token");
    state.token = storedToken ?? undefined;
  },
  setOrganization(id?: string | null) {
    state.organizationId = id ?? undefined;
    if (typeof window !== "undefined") {
      if (id) {
        window.localStorage.setItem("organization_id", id);
      } else {
        window.localStorage.removeItem("organization_id");
      }
    }
    notifyListeners();
  },
  setBranding(branding: TenantBranding) {
    state.branding = branding;
    notifyListeners();
  },
  setUser(user: TenantState["user"]) {
    state.user = user;
    notifyListeners();
  },
  setFeatureFlag(key: string, value: boolean) {
    state.featureFlags[key] = value;
    notifyListeners();
  },
  setToken(token: string | null | undefined) {
    state.token = token ?? undefined;
    if (typeof window !== "undefined") {
      if (token) {
        window.localStorage.setItem("token", token);
      } else {
        window.localStorage.removeItem("token");
      }
    }
    notifyListeners();
  },
  reset() {
    state.branding = undefined;
    state.user = null;
    state.featureFlags = {};
    state.token = undefined;
    state.organizationId = undefined;
    if (typeof window !== "undefined") {
      window.localStorage.removeItem("token");
      window.localStorage.removeItem("organization_id");
    }
    notifyListeners();
  },
  subscribe(listener: () => void): () => void {
    listeners.add(listener);
    return () => {
      listeners.delete(listener);
    };
  },
};
