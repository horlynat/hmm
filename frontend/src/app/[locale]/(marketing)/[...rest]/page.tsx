import { notFound } from "next/navigation";

/**
 * Toute adresse publique inconnue -> 404 rendue DANS le layout vitrine
 * ((marketing)/not-found.tsx, copie du prototype), plutôt que la 404 nue de
 * [locale]/not-found.tsx. Les routes réelles, plus spécifiques, ont priorité.
 */
export default function UnknownPublicPage() {
  notFound();
}
