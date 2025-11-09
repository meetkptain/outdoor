import type { HTMLAttributes } from "react";
import { getCurrentPalette } from "./theme";

export interface LoaderProps extends HTMLAttributes<HTMLSpanElement> {
  size?: number;
  label?: string;
}

export function Loader({ size = 24, label = "Chargement…", style, ...props }: LoaderProps) {
  const palette = getCurrentPalette();
  const strokeWidth = Math.max(2, Math.round(size / 8));

  return (
    <span
      role="status"
      aria-live="polite"
      style={{
        display: "inline-flex",
        alignItems: "center",
        gap: "0.5rem",
        color: palette.primary,
        ...style,
      }}
      {...props}
    >
      <svg
        width={size}
        height={size}
        viewBox="0 0 50 50"
        aria-hidden="true"
        focusable="false"
      >
        <circle
          cx="25"
          cy="25"
          r="20"
          fill="none"
          stroke="rgba(37, 99, 235, 0.15)"
          strokeWidth={strokeWidth}
        />
        <path
          d="M45 25c0-11.045-8.955-20-20-20"
          fill="none"
          stroke={palette.primary}
          strokeWidth={strokeWidth}
          strokeLinecap="round"
        >
          <animateTransform
            attributeName="transform"
            type="rotate"
            from="0 25 25"
            to="360 25 25"
            dur="1s"
            repeatCount="indefinite"
          />
        </path>
      </svg>
      <span style={{ fontSize: "0.9rem" }}>{label}</span>
    </span>
  );
}

