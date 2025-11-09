import { render, screen } from "@testing-library/react";
import { describe, it, expect } from "vitest";
import { afterEach } from "vitest";
import { Button } from "../ui/Button";
import { tenantStore } from "../store/tenant";

describe("Button component", () => {
  afterEach(() => {
    tenantStore.reset();
  });

  it("renders label and responds to loading state", () => {
    tenantStore.setBranding({
      primaryColor: "#2563eb",
      secondaryColor: "#1f2937",
    });

    render(<Button isLoading>Envoyer</Button>);

    expect(screen.getByText("Loading…")).toBeInTheDocument();
    expect(screen.queryByText("Envoyer")).not.toBeInTheDocument();
  });

  it("renders children when not loading", () => {
    render(<Button>Valider</Button>);
    expect(screen.getByRole("button", { name: "Valider" })).toBeInTheDocument();
  });
});

