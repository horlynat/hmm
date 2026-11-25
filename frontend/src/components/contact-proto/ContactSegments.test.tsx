import { describe, expect, it, vi } from "vitest";
import { fireEvent, render, screen } from "@testing-library/react";
import fr from "@/messages/fr.json";

vi.mock("next-intl", async (importOriginal) => {
  const actual = await importOriginal<typeof import("next-intl")>();
  return {
    ...actual,
    useTranslations: (namespace: string) => actual.createTranslator({ locale: "fr", messages: fr, namespace: namespace as never }),
  };
});
// Les deux panneaux sont branchés sur des actions serveur : seul compte ici
// lequel est affiché.
vi.mock("./QuoteWizardProto", () => ({ QuoteWizardProto: () => <div data-testid="quest-pane" /> }));
vi.mock("./QuickMessageForm", () => ({ QuickMessageForm: () => <div data-testid="direct-pane" /> }));

const { ContactSegments } = await import("./ContactSegments");

/**
 * `/contact` s'ouvre sur le questionnaire de devis ; `/contact?mode=direct`
 * (lien « Envoyer un message » de l'assistant, pour un recruteur ou une
 * question hors offre) ouvre directement le message rapide.
 */
describe("ContactSegments", () => {
  it("ouvre le questionnaire de devis par défaut", () => {
    render(<ContactSegments offers={[]} />);

    expect(screen.getByTestId("quest-pane")).toBeInTheDocument();
    expect(screen.queryByTestId("direct-pane")).not.toBeInTheDocument();
    expect(document.getElementById("ctab-quest")).toHaveAttribute("aria-selected", "true");
  });

  it("ouvre le message rapide quand initialMode vaut direct, et l'onglet le reflète", () => {
    render(<ContactSegments offers={[]} initialMode="direct" />);

    expect(screen.getByTestId("direct-pane")).toBeInTheDocument();
    expect(screen.queryByTestId("quest-pane")).not.toBeInTheDocument();
    expect(document.getElementById("ctab-direct")).toHaveAttribute("aria-selected", "true");
  });

  it("le visiteur peut toujours revenir au questionnaire", () => {
    render(<ContactSegments offers={[]} initialMode="direct" />);

    fireEvent.click(document.getElementById("ctab-quest")!);

    expect(screen.getByTestId("quest-pane")).toBeInTheDocument();
  });
});
