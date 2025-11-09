declare global {
  interface Window {
    __axe_initialized__?: boolean;
  }
}

import React from "react";
import ReactDOM from "react-dom";

let initializing: Promise<void> | null = null;
const AXE_PACKAGE_NAME = "@axe-core/react";

export function initAxe() {
  if (typeof window === "undefined") {
    return;
  }
  const enableAxe =
    typeof import.meta !== "undefined" &&
    Boolean(
      (import.meta as { env?: { VITE_ENABLE_AXE?: string } }).env
        ?.VITE_ENABLE_AXE === "true",
    );
  if (!enableAxe) {
    return;
  }
  if (window.__axe_initialized__) {
    return;
  }
  if (initializing) {
    return;
  }

  initializing = import(/* @vite-ignore */ AXE_PACKAGE_NAME)
    .then((mod) => {
      const axeModule =
        (mod && typeof mod === "object" && "default" in mod
          ? (mod as { default: unknown }).default
          : mod) ?? undefined;
      const axe = typeof axeModule === "function" ? axeModule : undefined;
      if (typeof axe === "function") {
        axe(React, ReactDOM, 1000);
        window.__axe_initialized__ = true;
      }
    })
    .catch((error) => {
      if (
        typeof import.meta !== "undefined" &&
        (import.meta as { env?: { MODE?: string } }).env?.MODE !== "test"
      ) {
        // eslint-disable-next-line no-console
        console.warn(
          "[axe] Optional dependency '@axe-core/react' is not available. Skip accessibility auto-checks.",
          error instanceof Error ? error.message : error,
        );
      }
    })
    .finally(() => {
      initializing = null;
    });
}


