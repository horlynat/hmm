import { afterEach, describe, expect, it, vi } from "vitest";
import { fireEvent, render, screen, waitFor } from "@testing-library/react";
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

function input() {
  return screen.getByPlaceholderText("Écrire un message") as HTMLInputElement;
}

function send(text: string) {
  fireEvent.change(input(), { target: { value: text } });
  fireEvent.click(screen.getByLabelText("Envoyer"));
}

function jsonResponse(body: AiDemoResult): Response {
  return { json: async () => body } as Response;
}

/**
 * `getByText`/`getByRole` comparent le texte normalisé du DOM (espaces
 * collapsées, y compris l'espace insécable « ? » du français) à la chaîne
 * donnée telle quelle, SANS la normaliser elle aussi (cf. @testing-library/
 * dom `matches()` : seul `textToMatch` passe par le normaliseur). Les
 * chaînes de fr.json contenant une espace insécable avant « ? » ne
 * correspondraient donc jamais sans ce même collapse appliqué ici.
 */
function norm(s: string): string {
  return s.replace(/\s+/g, " ");
}

/**
 * Démo WhatsApp de l'accueil branchée sur la vraie IA (POST
 * /api/ai-assistant/demo), avec le jeu de réponses scriptées en filet de
 * secours.
 */
describe("HomeDemo", () => {
  afterEach(() => vi.restoreAllMocks());

  it("affiche l'échange scripté initial de la boutique d'exemple", () => {
    render(<HomeDemo />);
    // Comparé au texte de fr.json, jamais retapé : la ponctuation française
    // (apostrophes, espaces fines) rend une recopie manuelle peu fiable.
    expect(screen.getByText(norm(fr.landing.demo.user1))).toBeInTheDocument();
    expect(screen.getByText(fr.landing.demo.bot1)).toBeInTheDocument();
  });

  it("le champ se vide dès l'envoi, sans attendre la réponse de l'IA", () => {
    vi.spyOn(global, "fetch").mockReturnValue(new Promise(() => {}));
    render(<HomeDemo />);
    send("Une question ?");
    expect(input().value).toBe("");
    expect(screen.getByText("Une question ?")).toBeInTheDocument();
  });

  it("un second envoi pendant qu'une réponse est en cours est ignoré (jamais d'historique invalide envoyé à la démo)", async () => {
    let resolveFetch!: (r: Response) => void;
    const fetchSpy = vi.spyOn(global, "fetch").mockReturnValue(new Promise((resolve) => (resolveFetch = resolve)));
    render(<HomeDemo />);

    send("Première question ?");
    send("Seconde question ?");

    expect(fetchSpy).toHaveBeenCalledTimes(1);
    expect(screen.queryByText("Seconde question ?")).not.toBeInTheDocument();

    resolveFetch(jsonResponse({ ok: true, answer: "Réponse.", event: null }));
    await waitFor(() => expect(screen.getByText("Réponse.")).toBeInTheDocument());
  });

  it("affiche la réponse de l'IA et compte une « nouvelle commande » sur l'évènement order", async () => {
    vi.spyOn(global, "fetch").mockResolvedValue(jsonResponse({ ok: true, answer: "Deux sacs de riz, c'est noté.", event: "order" }));
    render(<HomeDemo />);
    send("Deux sacs, s'il vous plaît");

    await waitFor(() => expect(screen.getByText("Deux sacs de riz, c'est noté.")).toBeInTheDocument());
    expect(document.querySelector("#k1")).toHaveTextContent("48");
    expect(document.querySelector("#k2")).toHaveTextContent("10");
    expect(document.querySelector("#k3")).toHaveTextContent("2");
    expect(screen.getByText(fr.landing.demo.newOrder[0])).toBeInTheDocument();
  });

  it("compte une « demande transmise » sur l'évènement handover, sans toucher au compteur de commandes", async () => {
    vi.spyOn(global, "fetch").mockResolvedValue(jsonResponse({ ok: true, answer: "Je transmets au gérant.", event: "handover" }));
    render(<HomeDemo />);
    send("50 cartons, prix de gros ?");

    await waitFor(() => expect(screen.getByText("Je transmets au gérant.")).toBeInTheDocument());
    expect(document.querySelector("#k1")).toHaveTextContent("48");
    expect(document.querySelector("#k2")).toHaveTextContent("9");
    expect(document.querySelector("#k3")).toHaveTextContent("3");
    expect(screen.getByText(fr.landing.demo.handover[0])).toBeInTheDocument();
  });

  it("une réponse de l'IA sans évènement incrémente seulement les messages traités", async () => {
    vi.spyOn(global, "fetch").mockResolvedValue(jsonResponse({ ok: true, answer: "Nous sommes ouverts du lundi au samedi.", event: null }));
    render(<HomeDemo />);
    send("Vos horaires ?");

    await waitFor(() => expect(screen.getByText("Nous sommes ouverts du lundi au samedi.")).toBeInTheDocument());
    expect(document.querySelector("#k1")).toHaveTextContent("48");
    expect(document.querySelector("#k2")).toHaveTextContent("9");
    expect(document.querySelector("#k3")).toHaveTextContent("2");
  });

  it("retombe sur la réponse scriptée (et son propre évènement) quand l'IA refuse", async () => {
    vi.spyOn(global, "fetch").mockResolvedValue(jsonResponse({ ok: false }));
    render(<HomeDemo />);
    send("Oui, 2 sacs");

    await waitFor(() => expect(screen.getByText(fr.landing.demo.answers.order)).toBeInTheDocument());
    expect(document.querySelector("#k2")).toHaveTextContent("10");
    expect(screen.getByText(fr.landing.demo.newOrder[0])).toBeInTheDocument();
  });

  it("retombe sur la réponse scriptée en cas de panne réseau, jamais un plantage", async () => {
    vi.spyOn(global, "fetch").mockRejectedValue(new Error("network"));
    render(<HomeDemo />);
    send("Vos horaires ?");

    await waitFor(() => expect(screen.getByText(fr.landing.demo.answers.hours)).toBeInTheDocument());
  });

  it("envoie l'historique récent (bulles non vides, 6 derniers tours) à la démo", async () => {
    const fetchSpy = vi.spyOn(global, "fetch").mockResolvedValue(jsonResponse({ ok: true, answer: "Réponse 1.", event: null }));
    render(<HomeDemo />);
    send("Une première question ?");
    await waitFor(() => expect(screen.getByText("Réponse 1.")).toBeInTheDocument());

    const [, body] = fetchSpy.mock.calls[0];
    const sent = JSON.parse(String((body as RequestInit).body));
    expect(sent.locale).toBe("fr");
    expect(sent.message).toBe("Une première question ?");
    // Échange scripté initial (3 bulles) envoyé comme historique, sans la
    // bulle de frappe en cours.
    expect(sent.history).toEqual([
      { role: "assistant", text: fr.landing.demo.welcome },
      { role: "user", text: fr.landing.demo.user1 },
      { role: "assistant", text: fr.landing.demo.bot1 },
    ]);
  });

  it("les réponses rapides envoient la même question que la saisie libre", async () => {
    const quick = fr.landing.demo.quick[2];
    const fetchSpy = vi.spyOn(global, "fetch").mockResolvedValue(jsonResponse({ ok: true, answer: "Oui, nous livrons.", event: null }));
    render(<HomeDemo />);
    // `getByRole`'s name matcher uses an identity normalizer (no whitespace
    // collapse, unlike `getByText`) : la chaîne brute de fr.json (espace
    // insécable comprise) la matche directement, sans passer par `norm()`.
    fireEvent.click(screen.getByRole("button", { name: quick }));

    await waitFor(() => expect(screen.getByText("Oui, nous livrons.")).toBeInTheDocument());
    const [, body] = fetchSpy.mock.calls[0];
    expect(JSON.parse(String((body as RequestInit).body)).message).toBe(quick);
  });
});
