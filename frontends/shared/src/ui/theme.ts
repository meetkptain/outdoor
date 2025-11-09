import { tenantStore } from "../store/tenant";
import type { TenantBranding } from "../store/tenant";

export type ThemePalette = {
  primary: string;
  primaryForeground: string;
  secondary: string;
  secondaryForeground: string;
  neutral: string;
  surface: string;
  surfaceBorder: string;
  success: string;
  warning: string;
  danger: string;
  info: string;
};

const DEFAULT_PALETTE: ThemePalette = {
  primary: "#2563eb",
  primaryForeground: "#ffffff",
  secondary: "#1f2937",
  secondaryForeground: "#ffffff",
  neutral: "#6b7280",
  surface: "#ffffff",
  surfaceBorder: "rgba(15, 23, 42, 0.08)",
  success: "#16a34a",
  warning: "#d97706",
  danger: "#dc2626",
  info: "#0ea5e9",
};

function getBrightness(hexColor: string): number {
  const value = hexColor.replace("#", "");
  if (value.length !== 6) {
    return 0;
  }
  const r = parseInt(value.substring(0, 2), 16);
  const g = parseInt(value.substring(2, 4), 16);
  const b = parseInt(value.substring(4, 6), 16);
  return (r * 299 + g * 587 + b * 114) / 1000;
}

function getForegroundForColor(color: string, fallback = "#ffffff"): string {
  const brightness = getBrightness(color);
  return brightness > 150 ? "#0f172a" : fallback;
}

export function buildPalette(branding?: TenantBranding | undefined): ThemePalette {
  if (!branding) {
    return DEFAULT_PALETTE;
  }
  const primary = (branding.primaryColor as string | undefined) ?? DEFAULT_PALETTE.primary;
  const secondary =
    (branding.secondaryColor as string | undefined) ?? DEFAULT_PALETTE.secondary;

  return {
    ...DEFAULT_PALETTE,
    primary,
    secondary,
    primaryForeground: getForegroundForColor(primary),
    secondaryForeground: getForegroundForColor(secondary),
  };
}

export function getCurrentPalette(): ThemePalette {
  const { branding } = tenantStore.getState();
  return buildPalette(branding);
}

