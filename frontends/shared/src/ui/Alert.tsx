import type { HTMLAttributes, ReactNode } from "react";
import { getCurrentPalette } from "./theme";

export type AlertVariant = "info" | "success" | "warning" | "danger";

export interface AlertProps extends HTMLAttributes<HTMLDivElement> {
  variant?: AlertVariant;
  heading?: ReactNode;
  description?: ReactNode;
  icon?: ReactNode;
}

const variantMap = {
  info: {
    background: "rgba(14, 165, 233, 0.12)",
    color: "#0369a1",
  },
  success: {
    background: "rgba(22, 163, 74, 0.12)",
    color: "#15803d",
  },
  warning: {
    background: "rgba(217, 119, 6, 0.15)",
    color: "#b45309",
  },
  danger: {
    background: "rgba(220, 38, 38, 0.12)",
    color: "#b91c1c",
  },
} as const;

export function Alert({ variant = "info", heading, description, icon, style, children, ...props }: AlertProps) {
  const palette = getCurrentPalette();
  const chosen = variantMap[variant];

  return (
    <div
      role="status"
      style={{
        borderRadius: "0.9rem",
        padding: "1rem 1.25rem",
        border: `1px solid ${palette.surfaceBorder}`,
        background: chosen.background,
        color: chosen.color,
        display: "flex",
        gap: "0.75rem",
        alignItems: "flex-start",
        ...style,
      }}
      {...props}
    >
      {icon && (
        <span
          aria-hidden="true"
          style={{
            display: "inline-flex",
            marginTop: "0.1rem",
          }}
        >
          {icon}
        </span>
      )}
      <div style={{ display: "grid", gap: "0.25rem" }}>
        {heading && (
          <strong style={{ margin: 0, fontSize: "0.95rem" }}>{heading}</strong>
        )}
        {description && (
          <p style={{ margin: 0, fontSize: "0.9rem" }}>{description}</p>
        )}
        {children}
      </div>
    </div>
  );
}

