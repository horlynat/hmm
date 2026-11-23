import type { Metadata } from "next";
import { buildPageMetadata } from "@/lib/metadata";
import { LegalPage } from "@/components/legal-proto/LegalPage";
import { conditionsContent } from "@/content/legal/conditions";

export const dynamic = "force-static";

export async function generateMetadata({ params }: { params: Promise<{ locale: string }> }): Promise<Metadata> {
  const { locale } = await params;
  const c = conditionsContent[locale === "en" ? "en" : "fr"];
  return buildPageMetadata({ locale, pathname: "/conditions-de-prestation", title: c.title, description: c.lede });
}

export default async function ConditionsPage({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  const c = conditionsContent[locale === "en" ? "en" : "fr"];
  return <LegalPage locale={locale} path="/conditions-de-prestation" crumb={c.crumb} title={c.title} lede={c.lede} sections={c.sections} updatedLabel={c.updatedLabel} />;
}
