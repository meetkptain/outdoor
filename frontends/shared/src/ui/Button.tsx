import { forwardRef } from "react";
import type { ButtonHTMLAttributes, CSSProperties, ReactNode } from "react";
import { getCurrentPalette } from "./theme";

export type ButtonVariant = "primary" | "secondary" | "ghost";
export type ButtonSize = "sm" | "md" | "lg";

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: ButtonVariant;
  size?: ButtonSize;
  isLoading?: boolean;
  icon?: ReactNode;
}

const sizeStyles: Record<ButtonSize, CSSProperties> = {
  sm: {
    paddingBlock: "0.4rem",
    paddingInline: "0.8rem",
    fontSize: "0.85rem",
  },
  md: {
    paddingBlock: "0.55rem",
    paddingInline: "1rem",
    fontSize: "0.95rem",
  },
  lg: {
    paddingBlock: "0.75rem",
    paddingInline: "1.4rem",
    fontSize: "1rem",
  },
};

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(
  ({ variant = "primary", size = "md", isLoading = false, icon, children, disabled, style, ...props }, ref) => {
    const palette = getCurrentPalette();
    const baseStyle: CSSProperties = {
      borderRadius: "0.75rem",
      border: "1px solid transparent",
      fontWeight: 600,
      display: "inline-flex",
      alignItems: "center",
      justifyContent: "center",
      gap: "0.5rem",
      cursor: "pointer",
      transition: "transform 0.15s ease, box-shadow 0.15s ease, opacity 0.15s ease",
      boxShadow: "0 8px 20px -15px rgba(37, 99, 235, 0.7)",
      background: palette.primary,
      color: palette.primaryForeground,
    };

    const variants: Record<ButtonVariant, CSSProperties> = {
      primary: {
        background: palette.primary,
        color: palette.primaryForeground,
      },
      secondary: {
        background: palette.secondary,
        color: palette.secondaryForeground,
        boxShadow: "0 6px 18px -12px rgba(15, 23, 42, 0.6)",
      },
      ghost: {
        background: "transparent",
        color: palette.primary,
        borderColor: "rgba(37, 99, 235, 0.35)",
        boxShadow: "none",
      },
    };

    const disabledStyle: CSSProperties = disabled || isLoading
      ? {
          opacity: 0.65,
          cursor: "not-allowed",
          boxShadow: "none",
        }
      : {};

    return (
      <button
        ref={ref}
        style={{
          ...baseStyle,
          ...sizeStyles[size],
          ...variants[variant],
          ...disabledStyle,
          ...style,
        }}
        disabled={disabled || isLoading}
        {...props}
      >
        {isLoading ? (
          <span aria-live="polite" aria-busy="true">
            Loading…
          </span>
        ) : (
          <>
            {icon}
            {children}
          </>
        )}
      </button>
    );
  }
);

Button.displayName = "Button";

