import { describe, expect, it } from "vitest";
import { render, screen } from "@testing-library/react";
import { Badge } from "./Badge";

describe("Badge", () => {
  it("renders its children", () => {
    render(<Badge>Symfony</Badge>);
    expect(screen.getByText("Symfony")).toBeInTheDocument();
  });

  it("applies the outline variant class", () => {
    render(<Badge variant="outline">API Platform</Badge>);
    expect(screen.getByText("API Platform")).toHaveClass("badge-outline");
  });
});
