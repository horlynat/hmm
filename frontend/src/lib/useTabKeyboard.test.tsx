import { describe, expect, it, vi } from "vitest";
import { useState } from "react";
import { fireEvent, render } from "@testing-library/react";
import { useTabKeyboard } from "./useTabKeyboard";

const VALUES = ["a", "b", "c"] as const;
type V = (typeof VALUES)[number];

function Tabs({ onActivate }: { onActivate?: (v: V) => void }) {
  const [selected, setSelected] = useState<V>("a");
  const { tabProps } = useTabKeyboard(VALUES, selected);
  return (
    <div role="tablist" aria-label="Test">
      {VALUES.map((v) => (
        <button
          key={v}
          type="button"
          role="tab"
          data-testid={`tab-${v}`}
          {...tabProps(v)}
          onClick={() => {
            setSelected(v);
            onActivate?.(v);
          }}
        >
          {v}
        </button>
      ))}
    </div>
  );
}

describe("useTabKeyboard", () => {
  it("puts only the selected tab in the tab order", () => {
    const { getByTestId } = render(<Tabs />);
    expect(getByTestId("tab-a").getAttribute("tabindex")).toBe("0");
    expect(getByTestId("tab-b").getAttribute("tabindex")).toBe("-1");
    expect(getByTestId("tab-a").getAttribute("aria-selected")).toBe("true");
    expect(getByTestId("tab-b").getAttribute("aria-selected")).toBe("false");
  });

  it("moves focus with arrows (wrapping), Home and End — without activating", () => {
    const onActivate = vi.fn();
    const { getByTestId } = render(<Tabs onActivate={onActivate} />);
    getByTestId("tab-a").focus();

    fireEvent.keyDown(getByTestId("tab-a"), { key: "ArrowRight" });
    expect(document.activeElement).toBe(getByTestId("tab-b"));
    fireEvent.keyDown(getByTestId("tab-b"), { key: "ArrowRight" });
    expect(document.activeElement).toBe(getByTestId("tab-c"));
    fireEvent.keyDown(getByTestId("tab-c"), { key: "ArrowRight" });
    expect(document.activeElement).toBe(getByTestId("tab-a"));
    fireEvent.keyDown(getByTestId("tab-a"), { key: "ArrowLeft" });
    expect(document.activeElement).toBe(getByTestId("tab-c"));
    fireEvent.keyDown(getByTestId("tab-c"), { key: "Home" });
    expect(document.activeElement).toBe(getByTestId("tab-a"));
    fireEvent.keyDown(getByTestId("tab-a"), { key: "End" });
    expect(document.activeElement).toBe(getByTestId("tab-c"));

    // Activation manuelle : déplacer le focus ne sélectionne rien.
    expect(onActivate).not.toHaveBeenCalled();
    expect(getByTestId("tab-a").getAttribute("aria-selected")).toBe("true");
  });

  it("activation (click / Enter / Space) selects the tab and moves the tab stop", () => {
    const { getByTestId } = render(<Tabs />);
    fireEvent.click(getByTestId("tab-b"));
    expect(getByTestId("tab-b").getAttribute("aria-selected")).toBe("true");
    expect(getByTestId("tab-b").getAttribute("tabindex")).toBe("0");
    expect(getByTestId("tab-a").getAttribute("tabindex")).toBe("-1");
  });

  it("leaves other keys alone (e.g. Tab still leaves the tablist)", () => {
    const { getByTestId } = render(<Tabs />);
    getByTestId("tab-a").focus();
    const notPrevented = fireEvent.keyDown(getByTestId("tab-a"), { key: "Tab" });
    expect(notPrevented).toBe(true);
    expect(document.activeElement).toBe(getByTestId("tab-a"));
  });
});
