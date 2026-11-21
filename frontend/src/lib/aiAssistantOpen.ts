/**
 * Canal d'ouverture de l'assistant IA (AiAssistantWidget) depuis n'importe
 * quelle section du site, ex. le CTA « Discuter avec mon assistant IA » de
 * l'accueil.
 *
 * Le widget est chargé en différé (next/dynamic, ssr: false) : un clic qui
 * survient AVANT son chargement émettrait un évènement que personne n'écoute
 * encore. La demande est donc aussi mémorisée ici (`pending`), et le widget
 * la consomme à son montage. Ce module est importé à la fois par le bouton
 * (chargé tout de suite) et par le widget (chargé plus tard) : le bundler
 * n'en garde qu'une instance, ce qui partage `pending` entre les deux.
 *
 * Aucune donnée ne transite : c'est un simple signal d'ouverture.
 */
export const AI_ASSISTANT_OPEN_EVENT = "ai-assistant:open";

let pending = false;

export function requestAiAssistantOpen(): void {
  pending = true;
  window.dispatchEvent(new Event(AI_ASSISTANT_OPEN_EVENT));
}

/** Renvoie `true` (une seule fois) si une ouverture a été demandée. */
export function consumeAiAssistantOpenRequest(): boolean {
  const wasPending = pending;
  pending = false;
  return wasPending;
}
