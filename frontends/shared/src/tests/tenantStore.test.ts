import { describe, it, expect, beforeEach, vi } from "vitest";
import { tenantStore } from "../store/tenant";

describe("tenantStore", () => {
  beforeEach(() => {
    window.localStorage.clear();
    tenantStore.reset();
  });

  it("sets organization and persists to storage", () => {
    const unsubscribe = tenantStore.subscribe(vi.fn());
    tenantStore.setOrganization("42");

    expect(tenantStore.getState().organizationId).toBe("42");
    expect(window.localStorage.getItem("organization_id")).toBe("42");

    tenantStore.reset();
    unsubscribe();
  });

  it("notifies subscribers on updates", () => {
    const listener = vi.fn();
    const unsubscribe = tenantStore.subscribe(listener);

    tenantStore.setFeatureFlag("instant_booking", true);
    expect(listener).toHaveBeenCalled();

    tenantStore.setFeatureFlag("instant_booking", false);
    expect(listener).toHaveBeenCalledTimes(2);

    unsubscribe();
  });

  it("clears everything on reset", () => {
    tenantStore.setOrganization("99");
    tenantStore.setToken("token");
    tenantStore.setBranding({ primaryColor: "#123456" });
    tenantStore.setUser({
      id: 1,
      name: "Alice",
      email: "alice@example.com",
      role: "admin",
    });

    tenantStore.reset();

    const state = tenantStore.getState();
    expect(state.organizationId).toBeUndefined();
    expect(state.token).toBeUndefined();
    expect(state.user).toBeNull();
    expect(state.branding).toBeUndefined();
  });
});

