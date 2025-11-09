import type { LabelHTMLAttributes, ReactNode } from "react";
import { getCurrentPalette } from "./theme";

export interface FormFieldProps extends LabelHTMLAttributes<HTMLLabelElement> {
  label: ReactNode;
  hint?: ReactNode;
  error?: ReactNode;
  requiredIndicator?: ReactNode;
  required?: boolean;
}

export function FormField({ label, hint, error, requiredIndicator = "*", children, style, ...props }: FormFieldProps) {
  const palette = getCurrentPalette();
  const { required, ...rest } = props;

  const isError = Boolean(error);
  const captionColor = isError ? palette.danger : "rgba(100, 116, 139, 1)";

  return (
    <label
      style={{
        display: "grid",
        gap: "0.35rem",
        fontSize: "0.92rem",
        color: "rgba(15, 23, 42, 0.9)",
      ...style,
    }}
    {...rest}
    >
      <span style={{ display: "flex", alignItems: "center", gap: "0.35rem" }}>
        <span>{label}</span>
        {required && (
          <span
            aria-hidden="true"
            style={{ color: palette.danger, fontWeight: 600 }}
          >
            {requiredIndicator}
          </span>
        )}
      </span>
      {children}
      {hint && !error && (
        <span
          style={{
            color: captionColor,
            fontSize: "0.8rem",
          }}
        >
          {hint}
        </span>
      )}
      {error && (
        <span
          role="alert"
          style={{
            color: palette.danger,
            fontSize: "0.82rem",
            fontWeight: 500,
          }}
        >
          {error}
        </span>
      )}
    </label>
  );
}

