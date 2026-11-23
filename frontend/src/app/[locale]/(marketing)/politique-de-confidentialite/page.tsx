import type { Metadata } from "next";
import { buildPageMetadata } from "@/lib/metadata";
import { LegalPage } from "@/components/legal-proto/LegalPage";
import { confidentialiteContent } from "@/content/legal/confidentialite";

export const dynamic = "force-static";

export async function generateMetadata({ params }: { params: Promise<{ locale: string }> }): Promise<Metadata> {
  const { locale } = await params;
  const c = confidentialiteContent[locale === "en" ? "en" : "fr"];
  return buildPageMetadata({ locale, pathname: "/politique-de-confidentialite", title: c.title, description: c.lede });
}

export default async function ConfidentialitePage({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  const c = confidentialiteContent[locale === "en" ? "en" : "fr"];
  return <LegalPage locale={locale} path="/politique-de-confidentialite" crumb={c.crumb} title={c.title} lede={c.lede} sections={c.sections} updatedLabel={c.updatedLabel} />;
}
