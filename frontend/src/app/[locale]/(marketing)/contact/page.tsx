import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { buildPageMetadata } from "@/lib/metadata";
import { SiteContact } from "@/components/contact-proto/SiteContact";

// CSP stricte par nonce (cf. src/proxy.ts, src/lib/csp.ts) : un nonce ne
// peut être généré qu'au moment de la requête, donc cette page ne peut pas
// être prérendue statiquement.
export const dynamic = "force-dynamic";

export async function generateMetadata({ params }: { params: Promise<{ locale: string }> }): Promise<Metadata> {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: "contactPage" });
  return buildPageMetadata({
    locale,
    pathname: "/contact",
    title: t("metaTitle"),
    description: t("lede"),
  });
}

export default async function ContactPage({
  params,
  searchParams,
}: {
  params: Promise<{ locale: string }>;
  searchParams: Promise<{ offre?: string; mode?: string }>;
}) {
  const { locale } = await params;
  const { offre, mode } = await searchParams;
  // `?mode=direct` (lien « Envoyer un message » de l'assistant) ouvre l'onglet
  // Message rapide ; toute autre valeur garde le questionnaire de devis.
  return <SiteContact locale={locale} offerSlug={offre} initialMode={mode === "direct" ? "direct" : "quest"} />;
}
