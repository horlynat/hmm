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
  searchParams: Promise<{ offre?: string }>;
}) {
  const { locale } = await params;
  const { offre } = await searchParams;
  return <SiteContact locale={locale} offerSlug={offre} />;
}
