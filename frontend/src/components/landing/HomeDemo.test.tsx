import { afterEach, describe, expect, it, vi } from "vitest";
import { act, fireEvent, render, screen, waitFor } from "@testing-library/react";
import fr from "@/messages/fr.json";
import type { AiDemoResult } from "@/lib/types";

vi.mock("next-intl", async (importOriginal) => {
  const actual = await importOriginal<typeof import("next-intl")>();
  return {
    ...actual,
    useTranslations: (namespace: string) => actual.createTranslator({ locale: "fr", messages: fr, namespace: namespace as never }),
    useLocale: () => "fr",
  };
});
// `useReducedMotion` dépend de `window.matchMedia`, non polyfillé dans
// l'environnement de test ; à `true` pour désactiver le délai artificiel de
// frappe (900 ms) — ces tests portent sur le contenu, pas l'animation.
vi.mock("@/lib/useReducedMotion", () => ({
  useReducedMotion: () => true,
}));

const { HomeDemo } = await import("./HomeDemo");

const demo = fr.landing.demo;

/**
 * `getByText` normalise le texte du DOM (espaces, y compris l'espace
 * insécable française avant « ? » ou « : ») mais PAS la chaîne cherchée
 * (@testing-library/dom `matches()`) : on applique le même traitement ici.
 */
function norm(s: string): string {
  return s.replace(/\s+/g, " ");
}

function input() {
  return screen.getByPlaceholderText(demo.placeholder) as HTMLInputElement;
}

function send(text: string) {
  fireEvent.change(input(), { target: { value: text } });
  fireEvent.click(screen.getByLabelText(demo.send));
}

function jsonResponse(body: AiDemoResult): Response {
  return { json: async () => body } as Response;
}

function ok(answer: string, extra: Partial<{ event: "order" | "handover" | null; suggestions: string[] }> = {}): Response {
  return jsonResponse({ ok: true, answer, event: extra.event ?? null, suggestions: extra.suggestions ?? [] });
}

function quickReplies() {
  return Array.from(document.querySelectorAll(".qs button")).map((b) => b.textContent);
}

/**
 * Démo WhatsApp de l'accueil : comme dans la vraie vie, la boutique accueille
 * d'abord le client — aucun échange joué à sa place, aucune question figée ;
 * les réponses rapides viennent de l'IA, tirées de ce qu'il a écrit.
 */
describe("HomeDemo", () => {
  afterEach(() => vi.restoreAllMocks());

  it("n'affiche au départ que l'accueil de la boutique : aucun échange scripté, aucune réponse rapide", () => {
    render(<HomeDemo />);

    expect(screen.getByText(norm(demo.welcome))).toBeInTheDocument();
    expect(document.querySelectorAll(".bub")).toHaveLength(1);
    expect(document.querySelectorAll(".bu")).toHaveLength(0);
    expect(document.querySelector(".qs")).not.toBeInTheDocument();
  });

  it("invite à essayer et affiche l'en-tête d'un vrai WhatsApp (nom de la boutique, « en ligne »)", () => {
    render(<HomeDemo />);

    expect(screen.getByText(norm(demo.notice))).toBeInTheDocument();
    expect(screen.getByText(norm(demo.shop))).toBeInTheDocument();
    expect(screen.getByText(norm(demo.online))).toBeInTheDocument();
  });

  it("le champ se vide dès l'envoi, la bulle est horodatée et l'en-tête passe à « écrit… »", () => {
    vi.spyOn(global, "fetch").mockReturnValue(new Promise(() => {}));
    render(<HomeDemo />);
    send("Une question ?");

    expect(input().value).toBe("");
    const bubble = screen.getByText("Une question ?").closest(".bub");
    expect(bubble).toHaveClass("bu");
    expect(bubble?.querySelector(".wa-mt")?.textContent).toMatch(/\d{2}:\d{2}/);
    expect(screen.getByText(norm(demo.typingStatus))).toBeInTheDocument();
  });

  it("un second envoi pendant qu'une réponse est en cours est ignoré (jamais d'historique invalide envoyé à la démo)", async () => {
    let resolveFetch!: (r: Response) => void;
    const fetchSpy = vi.spyOn(global, "fetch").mockReturnValue(new Promise((resolve) => (resolveFetch = resolve)));
    render(<HomeDemo />);

    send("Première question ?");
    send("Seconde question ?");

    expect(fetchSpy).toHaveBeenCalledTimes(1);
    expect(screen.queryByText("Seconde question ?")).not.toBeInTheDocument();

    resolveFetch(ok("Réponse."));
    await waitFor(() => expect(screen.getByText("Réponse.")).toBeInTheDocument());
    expect(screen.getByText(norm(demo.online))).toBeInTheDocument();
  });

  it("affiche les réponses rapides proposées par l'IA, et en cliquer une l'envoie", async () => {
    const fetchSpy = vi
      .spyOn(global, "fetch")
      .mockResolvedValueOnce(ok("Oui, sac de 25 kg à 18 000 FCFA.", { suggestions: ["Je prends 2 sacs", "Livré à Bacongo ?"] }))
      .mockResolvedValueOnce(ok("C'est noté !", { suggestions: [] }));
    render(<HomeDemo />);
    send("Vous avez du riz ?");

    await waitFor(() => expect(quickReplies()).toEqual(["Je prends 2 sacs", "Livré à Bacongo ?"]));

    fireEvent.click(screen.getByRole("button", { name: "Je prends 2 sacs" }));
    expect(quickReplies()).toEqual([]);
    await waitFor(() => expect(screen.getByText("C'est noté !")).toBeInTheDocument());

    const [, second] = fetchSpy.mock.calls[1];
    expect(JSON.parse(String((second as RequestInit).body)).message).toBe("Je prends 2 sacs");
    expect(document.querySelector(".qs")).not.toBeInTheDocument();
  });

  it("affiche la réponse de l'IA et compte une « nouvelle commande » sur l'évènement order", async () => {
    vi.spyOn(global, "fetch").mockResolvedValue(ok("Deux sacs de riz, c'est noté.", { event: "order" }));
    render(<HomeDemo />);
    send("Deux sacs, s'il vous plaît");

    await waitFor(() => expect(screen.getByText("Deux sacs de riz, c'est noté.")).toBeInTheDocument());
    expect(document.querySelector("#k1")).toHaveTextContent("48");
    expect(document.querySelector("#k2")).toHaveTextContent("10");
    expect(document.querySelector("#k3")).toHaveTextContent("2");
    expect(screen.getByText(norm(demo.newOrder[0]))).toBeInTheDocument();
  });

  it("compte une « demande transmise » sur l'évènement handover, sans toucher au compteur de commandes", async () => {
    vi.spyOn(global, "fetch").mockResolvedValue(ok("Je transmets au gérant.", { event: "handover" }));
    render(<HomeDemo />);
    send("50 cartons, prix de gros ?");

    await waitFor(() => expect(screen.getByText("Je transmets au gérant.")).toBeInTheDocument());
    expect(document.querySelector("#k1")).toHaveTextContent("48");
    expect(document.querySelector("#k2")).toHaveTextContent("9");
    expect(document.querySelector("#k3")).toHaveTextContent("3");
    expect(screen.getByText(norm(demo.handover[0]))).toBeInTheDocument();
  });

  it("retombe sur la réponse scriptée quand l'IA refuse — sans aucune réponse rapide figée", async () => {
    vi.spyOn(global, "fetch").mockResolvedValue(jsonResponse({ ok: false }));
    render(<HomeDemo />);
    send("Oui, 2 sacs");

    await waitFor(() => expect(screen.getByText(norm(demo.answers.order))).toBeInTheDocument());
    expect(document.querySelector("#k2")).toHaveTextContent("10");
    expect(document.querySelector(".qs")).not.toBeInTheDocument();
  });

  it("retombe sur la réponse scriptée en cas de panne réseau, jamais un plantage", async () => {
    vi.spyOn(global, "fetch").mockRejectedValue(new Error("network"));
    render(<HomeDemo />);
    send("Vos horaires ?");

    await waitFor(() => expect(screen.getByText(norm(demo.answers.hours))).toBeInTheDocument());
  });

  it("n'envoie que l'accueil comme historique au premier message (rien n'est inventé côté client)", async () => {
    const fetchSpy = vi.spyOn(global, "fetch").mockResolvedValue(ok("Réponse 1."));
    render(<HomeDemo />);
    send("Une première question ?");
    await waitFor(() => expect(screen.getByText("Réponse 1.")).toBeInTheDocument());

    const sent = JSON.parse(String((fetchSpy.mock.calls[0][1] as RequestInit).body));
    expect(sent.locale).toBe("fr");
    expect(sent.message).toBe("Une première question ?");
    expect(sent.history).toEqual([{ role: "assistant", text: demo.welcome }]);
  });

  it("après deux échanges, propose d'avoir le même assistant (lien WhatsApp de commande)", async () => {
    vi.spyOn(global, "fetch").mockResolvedValueOnce(ok("Réponse A.")).mockResolvedValueOnce(ok("Réponse B."));
    render(<HomeDemo />);

    send("Question A ?");
    await waitFor(() => expect(screen.getByText("Réponse A.")).toBeInTheDocument());
    expect(screen.queryByText(norm(demo.ctaText), { exact: false })).not.toBeInTheDocument();

    send("Question B ?");
    await waitFor(() => expect(screen.getByText("Réponse B.")).toBeInTheDocument());
    const link = screen.getByRole("link", { name: demo.order });
    expect(link.getAttribute("href")).toContain("wa.me");
    expect(screen.getByText(norm(demo.ctaText), { exact: false })).toBeInTheDocument();
  });

  it("horodate le message d'accueil une fois monté côté client", async () => {
    render(<HomeDemo />);
    await act(async () => {
      await new Promise((r) => requestAnimationFrame(() => r(null)));
    });
    const welcome = screen.getByText(norm(demo.welcome)).closest(".bub");
    expect(welcome?.querySelector(".wa-mt")?.textContent).toMatch(/\d{2}:\d{2}/);
  });
});
