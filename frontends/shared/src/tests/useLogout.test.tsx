import { describe, it, expect, beforeEach, vi } from "vitest";
import { renderHook, act } from "@testing-library/react";
import { useLogout } from "../auth/useLogout";
import { tenantStore } from "../store/tenant";

vi.mock("../api/client", () => ({
  default: {
    post: vi.fn(),
  },
}));

import apiClient from "../api/client";

const mockedPost = apiClient.post as unknown as ReturnType<typeof vi.fn>;

describe("useLogout", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    tenantStore.setToken("token");
    tenantStore.setUser({
      id: 1,
      name: "Demo Admin",
      email: "admin@example.com",
      role: "admin",
    });
    tenantStore.setOrganization("42");
  });

  it("clears tenant state on logout", async () => {
    mockedPost.mockResolvedValue({ data: { success: true } });
    const { result } = renderHook(() => useLogout());

    await act(async () => {
      await result.current.logout();
    });

    const state = tenantStore.getState();
    expect(state.token).toBeUndefined();
    expect(state.organizationId).toBeUndefined();
    expect(state.user).toBeNull();
    expect(result.current.isSuccess).toBe(true);
  });

  it("still clears state when API request fails", async () => {
    mockedPost.mockRejectedValue(new Error("Network down"));

    const { result } = renderHook(() => useLogout());

    await act(async () => {
      await result.current.logout().catch(() => {});
    });

    expect(tenantStore.getState().token).toBeUndefined();
  });
});

