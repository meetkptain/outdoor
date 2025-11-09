import { describe, it, expect, beforeEach, vi } from "vitest";
import { renderHook, act } from "@testing-library/react";
import { useLogin } from "../auth/useLogin";
import { tenantStore } from "../store/tenant";

vi.mock("../api/client", () => ({
  default: {
    post: vi.fn(),
  },
}));

import apiClient from "../api/client";

const mockedPost = apiClient.post as unknown as ReturnType<typeof vi.fn>;

describe("useLogin", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    window.localStorage.clear();
    tenantStore.reset();
  });

  it("persists token, user and organization on success", async () => {
    mockedPost.mockResolvedValue({
      data: {
        data: {
          token: "tok_123",
          user: {
            id: 1,
            name: "Demo Admin",
            email: "admin@example.com",
            role: "admin",
          },
          organization: {
            id: 77,
          },
          branding: {
            primaryColor: "#123456",
          },
          feature_flags: {
            instant_booking: true,
          },
        },
      },
    });

    const { result } = renderHook(() => useLogin());

    await act(async () => {
      await result.current.login({
        email: "admin@example.com",
        password: "password",
      });
    });

    const state = tenantStore.getState();
    expect(state.token).toBe("tok_123");
    expect(state.user?.email).toBe("admin@example.com");
    expect(state.organizationId).toBe("77");
    expect(state.featureFlags.instant_booking).toBe(true);
    expect(result.current.isSuccess).toBe(true);
    expect(result.current.error).toBeNull();
  });

  it("exposes error state when login fails", async () => {
    mockedPost.mockRejectedValue(new Error("Invalid credentials"));

    const { result } = renderHook(() => useLogin());

    await act(async () => {
      await result.current
        .login({
          email: "admin@example.com",
          password: "bad",
        })
        .catch(() => {});
    });

    expect(result.current.isError).toBe(true);
    expect(result.current.error).toBe("Invalid credentials");
    expect(tenantStore.getState().token).toBeUndefined();
  });
});

