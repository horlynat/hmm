import { siteConfig } from "@/config/site";

/**
 * Lien WhatsApp vers le numéro du site, avec un message pré-rempli
 * optionnel (ex. « Bonjour Horlynat, je suis intéressé par l'offre … »).
 *
 * Le message est TOUJOURS encodé (encodeURIComponent) : un titre d'offre
 * contenant `&`, `#`, `?` ou `=` (contenu saisi dans le back-office) ne doit
 * jamais pouvoir ajouter un paramètre ni tronquer l'URL. On repart de
 * `siteConfig.whatsappUrl` analysé par `URL` plutôt que d'y concaténer une
 * chaîne, pour la même raison.
 */
export function whatsappLink(message?: string): string {
  const url = new URL(siteConfig.whatsappUrl);
  if (message && message.trim() !== "") {
    // encodeURIComponent (espaces → %20, format documenté par WhatsApp)
    // plutôt qu'URLSearchParams (espaces → +) ; il encode aussi & # ? =.
    url.search = `text=${encodeText(message)}`;
  }
  return url.toString();
}

/**
 * `encodeURIComponent` lève une URIError sur un « surrogate » UTF-16 isolé
 * (titre corrompu) : ce rendu ferait alors échouer toute la page. Repli sur
 * URLSearchParams, qui le remplace par U+FFFD sans lever.
 */
function encodeText(message: string): string {
  try {
    return encodeURIComponent(message);
  } catch {
    return new URLSearchParams({ text: message }).toString().slice("text=".length);
  }
}
