import { permanentRedirect } from "next/navigation";
import { getPathname } from "@/i18n/navigation";

/**
 * Refonte : « mot de passe oublié » est un état de la carte /connexion
 * (comme dans le prototype, `L.step===3`), pas une route dédiée.
 * Redirection permanente, pour conserver les anciens liens et le
 * référencement (cf. inscription/page.tsx).
 */
export default async function Page({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  permanentRedirect(`${getPathname({ locale, href: "/connexion" })}?view=forgot`);
}
