import type { HTMLAttributes, ReactNode } from "react";
import { getCurrentPalette } from "./theme";

export interface CardProps extends HTMLAttributes<HTMLDivElement> {
  heading?: ReactNode;
  subheading?: ReactNode;
  footer?: ReactNode;
}

export function Card({ heading, subheading, footer, children, style, ...props }: CardProps) {
  const palette = getCurrentPalette();

  return (
    <section
      style={{
        background: palette.surface,
        borderRadius: "1rem",
        border: `1px solid ${palette.surfaceBorder}`,
        padding: "1.5rem",
        boxShadow: "0 15px 45px -30px rgba(15, 23, 42, 0.35)",
        display: "grid",
        gap: "1rem",
        ...style,
      }}
      {...props}
    >
      {(heading || subheading) && (
        <header>
          {heading && (
            <h2
              style={{
                margin: 0,
                fontSize: "1.125rem",
                fontWeight: 600,
              }}
            >
              {heading}
            </h2>
          )}
          {subheading && (
            <p
              style={{
                margin: "0.35rem 0 0",
                color: "rgba(71, 85, 105, 1)",
                fontSize: "0.9rem",
              }}
            >
              {subheading}
            </p>
          )}
        </header>
      )}

      <div>{children}</div>

      {footer && (
        <footer
          style={{
            marginTop: "0.5rem",
            paddingTop: "0.75rem",
            borderTop: `1px solid ${palette.surfaceBorder}`,
          }}
        >
          {footer}
        </footer>
      )}
    </section>
  );
}

