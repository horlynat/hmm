import { permanentRedirect } from "next/navigation";
import { getPathname } from "@/i18n/navigation";

/**
 * Refonte : la rubrique « réalisations » est une section de la page À propos
 * (comme dans le prototype). Redirection permanente vers l'ancre, pour
 * conserver les anciens liens et le référencement (cf. competences/page.tsx
 * pour le choix de `next/navigation` plutôt que le wrapper next-intl).
 */
export default async function Page({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  permanentRedirect(`${getPathname({ locale, href: "/a-propos" })}#realisations`);
}
