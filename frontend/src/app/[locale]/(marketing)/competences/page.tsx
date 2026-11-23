import { permanentRedirect } from "next/navigation";
import { getPathname } from "@/i18n/navigation";

/**
 * Refonte : la rubrique « compétences » est une section de la page À propos
 * (comme dans le prototype). Redirection permanente vers l'ancre, pour
 * conserver les anciens liens et le référencement.
 *
 * `next/navigation` directement (pas le wrapper next-intl) : un fragment
 * (`#…`) n'est jamais envoyé au serveur par le navigateur, donc next-intl ne
 * type pas `href` avec `hash` pour une redirection — il est ajouté ici au
 * chemin déjà localisé, pour que le navigateur y défile après coup.
 */
export default async function Page({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  permanentRedirect(`${getPathname({ locale, href: "/a-propos" })}#competences`);
}
