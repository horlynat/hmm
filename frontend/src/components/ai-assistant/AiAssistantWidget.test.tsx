import { afterEach, describe, expect, it, vi } from "vitest";
import { act, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { AiAssistantWidget } from "./AiAssistantWidget";
import { AI_ASSISTANT_OPEN_EVENT, consumeAiAssistantOpenRequest, requestAiAssistantOpen } from "@/lib/aiAssistantOpen";
import type { AiAssistantEntry, AiAssistantSettings } from "@/lib/types";

vi.mock("next-intl", () => ({
  useTranslations: () => (key: string) => key,
  useLocale: () => "fr",
}));

const settings: AiAssistantSettings = {
  greeting: "Bonjour, je suis l'assistant.",
  fallback: "Je n'ai pas pu répondre, réessayez plus tard.",
};

const entries: AiAssistantEntry[] = [
  { id: 1, chipLabel: "Qui est Horlynat ?", keywords: ["horlynat", "qui"], answer: "Réponse locale à propos de Horlynat.", sortOrder: 1 },
  { id: 2, chipLabel: "Ses compétences", keywords: ["compétence", "competence"], answer: "Réponse locale sur les compétences.", sortOrder: 2 },
];

function openWidget() {
  fireEvent.click(screen.getByRole("button", { name: "title" }));
}

function askFreeText(question: string) {
  fireEvent.change(screen.getByPlaceholderText("placeholder"), { target: { value: question } });
  fireEvent.click(screen.getByRole("button", { name: "send" }));
}

function mockChatResponse(body: { ok: true; answer: string; suggestions: string[] }) {
  return { json: async () => body } as Response;
}

/**
 * Couvre le bug rapporté : la barre de puces restait figée sur les 6 entrées
 * statiques (App\Entity\AiAssistantEntry) pendant toute la conversation, sans
 * jamais refléter l'échange réel avec Claude. Cf. AiAssistantChatProcessor
 * ::buildSystemPrompt (backend) qui génère désormais les suggestions.
 */
describe("AiAssistantWidget", () => {
  afterEach(() => {
    vi.restoreAllMocks();
    // Isolation : aucune demande d'ouverture ne déborde sur le test suivant.
    consumeAiAssistantOpenRequest();
  });

  it("affiche les puces de démarrage dès l'ouverture, avant toute frappe (zéro friction au premier clic)", () => {
    render(<AiAssistantWidget settings={settings} entries={entries} />);
    openWidget();

    expect(screen.getByRole("button", { name: "Qui est Horlynat ?" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Ses compétences" })).toBeInTheDocument();
  });

  it("suggère les puces FAQ correspondantes pendant la frappe, avant tout échange réel", () => {
    render(<AiAssistantWidget settings={settings} entries={entries} />);
    openWidget();
    const field = screen.getByPlaceholderText("placeholder");

    fireEvent.change(field, { target: { value: "qui est" } });
    expect(screen.getByRole("button", { name: "Qui est Horlynat ?" })).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Ses compétences" })).not.toBeInTheDocument();

    fireEvent.change(field, { target: { value: "ses compétences" } });
    expect(screen.getByRole("button", { name: "Ses compétences" })).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Qui est Horlynat ?" })).not.toBeInTheDocument();

    // Champ vidé, avant tout échange réel : retour aux puces de démarrage (toutes).
    fireEvent.change(field, { target: { value: "" } });
    expect(screen.getByRole("button", { name: "Qui est Horlynat ?" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Ses compétences" })).toBeInTheDocument();
  });

  it("remplace les puces statiques par les suggestions contextuelles après un vrai échange", async () => {
    vi.spyOn(global, "fetch").mockResolvedValue(
      mockChatResponse({
        ok: true,
        answer: "Oui, il peut travailler sur un projet e-commerce.",
        suggestions: [
          "Quel est son budget habituel pour ce type de projet ?",
          "Comment se déroule une première prise de contact ?",
        ],
      }),
    );

    render(<AiAssistantWidget settings={settings} entries={entries} />);
    openWidget();
    askFreeText("Peut-il travailler sur un projet e-commerce ?");

    await waitFor(() => {
      expect(screen.getByText("Oui, il peut travailler sur un projet e-commerce.")).toBeInTheDocument();
    });

    // Le bug : ces puces génériques restaient affichées indéfiniment.
    expect(screen.queryByRole("button", { name: "Qui est Horlynat ?" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Ses compétences" })).not.toBeInTheDocument();

    // Le correctif : des suggestions contextuelles prennent leur place.
    expect(
      screen.getByRole("button", { name: "Quel est son budget habituel pour ce type de projet ?" }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: "Comment se déroule une première prise de contact ?" }),
    ).toBeInTheDocument();
  });

  it("cliquer sur une suggestion dynamique relance une vraie question (pas une réponse en boîte)", async () => {
    const fetchMock = vi
      .spyOn(global, "fetch")
      .mockResolvedValueOnce(
        mockChatResponse({ ok: true, answer: "Première réponse.", suggestions: ["Question de suivi ?"] }),
      )
      .mockResolvedValueOnce(
        mockChatResponse({ ok: true, answer: "Deuxième réponse.", suggestions: ["Autre question ?"] }),
      );

    render(<AiAssistantWidget settings={settings} entries={entries} />);
    openWidget();
    askFreeText("Une première question ?");
    await waitFor(() => expect(screen.getByText("Première réponse.")).toBeInTheDocument());

    fireEvent.click(screen.getByRole("button", { name: "Question de suivi ?" }));

    await waitFor(() => {
      expect(screen.getByText("Deuxième réponse.")).toBeInTheDocument();
    });

    expect(fetchMock).toHaveBeenCalledTimes(2);
    const secondCallBody = JSON.parse(String(fetchMock.mock.calls[1][1]?.body));
    expect(secondCallBody.question).toBe("Question de suivi ?");
    // L'historique envoyé au backend inclut bien le premier échange.
    expect(secondCallBody.history).toHaveLength(3);
  });

  it("garde les suggestions précédentes si un tour ne renvoie rien plutôt que de vider la barre", async () => {
    vi.spyOn(global, "fetch")
      .mockResolvedValueOnce(
        mockChatResponse({ ok: true, answer: "Réponse initiale.", suggestions: ["Suggestion A ?", "Suggestion B ?"] }),
      )
      .mockResolvedValueOnce(mockChatResponse({ ok: true, answer: "Réponse de repli.", suggestions: [] }));

    render(<AiAssistantWidget settings={settings} entries={entries} />);
    openWidget();
    askFreeText("Première question ?");
    await waitFor(() => expect(screen.getByText("Réponse initiale.")).toBeInTheDocument());

    fireEvent.click(screen.getByRole("button", { name: "Suggestion A ?" }));
    await waitFor(() => expect(screen.getByText("Réponse de repli.")).toBeInTheDocument());

    expect(screen.getByRole("button", { name: "Suggestion A ?" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Suggestion B ?" })).toBeInTheDocument();
  });

  /**
   * Refonte R3 : le CTA « Discuter avec l'assistant » de la démo WhatsApp de
   * l'accueil ouvre ce widget via un évènement DOM (aucun contexte React ne
   * les relie, cf. AiAssistantWidget.tsx).
   */
  it("s'ouvre sur demande (requestAiAssistantOpen)", () => {
    render(<AiAssistantWidget settings={settings} entries={entries} />);

    expect(screen.getByRole("dialog")).toHaveClass("hidden");

    act(() => requestAiAssistantOpen());

    expect(screen.getByRole("dialog")).toHaveClass("flex");
    expect(screen.getByRole("dialog")).not.toHaveClass("hidden");
  });

  /**
   * Le widget est chargé en différé (next/dynamic) : un clic sur le CTA avant
   * son chargement ne doit pas être perdu.
   */
  it("rattrape au montage une demande faite avant son chargement", () => {
    act(() => requestAiAssistantOpen());

    render(<AiAssistantWidget settings={settings} entries={entries} />);

    // `flex` figure aussi dans les classes de base du panneau fermé : seule
    // l'absence de `hidden` prouve qu'il est ouvert.
    expect(screen.getByRole("dialog")).not.toHaveClass("hidden");
  });

  it("une demande n'est consommée qu'une fois (pas de réouverture au remontage)", () => {
    act(() => requestAiAssistantOpen());
    const { unmount } = render(<AiAssistantWidget settings={settings} entries={entries} />);
    unmount();

    render(<AiAssistantWidget settings={settings} entries={entries} />);

    expect(screen.getByRole("dialog")).toHaveClass("hidden");
  });

  it("un évènement brut, sans demande enregistrée, ne l'ouvre pas", () => {
    render(<AiAssistantWidget settings={settings} entries={entries} />);

    fireEvent(window, new Event(AI_ASSISTANT_OPEN_EVENT));
    fireEvent(window, new Event("unrelated-event"));

    expect(screen.getByRole("dialog")).toHaveClass("hidden");
  });

  it("retire son écouteur au démontage", () => {
    const { unmount } = render(<AiAssistantWidget settings={settings} entries={entries} />);
    unmount();

    // Échoue seulement si l'écouteur survit au démontage et touche un
    // composant détruit ; la demande restée en attente est purgée ensuite.
    expect(() => act(() => requestAiAssistantOpen())).not.toThrow();
    consumeAiAssistantOpenRequest();
  });
});
