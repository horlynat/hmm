import { permanentRedirect } from "next/navigation";
import { getPathname } from "@/i18n/navigation";

/**
 * Refonte : la création de compte est l'onglet « Créer un compte » de
 * /connexion (comme dans le prototype, qui n'a pas de route dédiée pour
 * l'inscription). Redirection permanente, pour conserver les anciens liens
 * et le référencement (cf. competences/page.tsx pour le choix de
 * `next/navigation` plutôt que le wrapper next-intl).
 */
export default async function Page({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  permanentRedirect(`${getPathname({ locale, href: "/connexion" })}?tab=up`);
}
