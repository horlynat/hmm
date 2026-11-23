import type { Metadata } from "next";
import { buildPageMetadata } from "@/lib/metadata";
import { LegalPage } from "@/components/legal-proto/LegalPage";
import { utilisationContent } from "@/content/legal/utilisation";

export const dynamic = "force-static";

export async function generateMetadata({ params }: { params: Promise<{ locale: string }> }): Promise<Metadata> {
  const { locale } = await params;
  const c = utilisationContent[locale === "en" ? "en" : "fr"];
  return buildPageMetadata({ locale, pathname: "/conditions-generales", title: c.title, description: c.lede });
}

export default async function UtilisationPage({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  const c = utilisationContent[locale === "en" ? "en" : "fr"];
  return <LegalPage locale={locale} path="/conditions-generales" crumb={c.crumb} title={c.title} lede={c.lede} sections={c.sections} updatedLabel={c.updatedLabel} />;
}
