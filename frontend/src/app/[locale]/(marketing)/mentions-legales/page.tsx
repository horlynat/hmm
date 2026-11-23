import type { Metadata } from "next";
import { buildPageMetadata } from "@/lib/metadata";
import { LegalPage } from "@/components/legal-proto/LegalPage";
import { mentionsLegalesContent } from "@/content/legal/mentions-legales";

export const dynamic = "force-static";

export async function generateMetadata({ params }: { params: Promise<{ locale: string }> }): Promise<Metadata> {
  const { locale } = await params;
  const c = mentionsLegalesContent[locale === "en" ? "en" : "fr"];
  return buildPageMetadata({ locale, pathname: "/mentions-legales", title: c.title, description: c.lede });
}

export default async function MentionsLegalesPage({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  const c = mentionsLegalesContent[locale === "en" ? "en" : "fr"];
  return <LegalPage locale={locale} path="/mentions-legales" crumb={c.crumb} title={c.title} lede={c.lede} sections={c.sections} updatedLabel={c.updatedLabel} />;
}
