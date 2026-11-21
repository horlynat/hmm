import { siteConfig } from "@/config/site";

/**
 * Lien WhatsApp vers le numéro du site, avec un message pré-rempli
 * optionnel (ex. « Bonjour Horlynat, je suis intéressé par l'offre … »).
 *
 * Le message est TOUJOURS encodé via URLSearchParams : un titre d'offre
 * contenant `&`, `#`, `?` ou `=` (contenu saisi dans le back-office) ne doit
 * jamais pouvoir ajouter un paramètre ni tronquer l'URL. On repart de
 * `siteConfig.whatsappUrl` analysé par `URL` plutôt que d'y concaténer une
 * chaîne, pour la même raison.
 */
export function whatsappLink(message?: string): string {
  const url = new URL(siteConfig.whatsappUrl);
  if (message && message.trim() !== "") {
    url.search = new URLSearchParams({ text: message }).toString();
  }
  return url.toString();
}
