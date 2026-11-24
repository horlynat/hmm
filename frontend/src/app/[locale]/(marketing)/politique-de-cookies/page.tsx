import type { Metadata } from "next";
import { buildPageMetadata } from "@/lib/metadata";
import { LegalPage } from "@/components/legal-proto/LegalPage";
import { cookiesContent } from "@/content/legal/cookies";

export const dynamic = "force-static";

export async function generateMetadata({ params }: { params: Promise<{ locale: string }> }): Promise<Metadata> {
  const { locale } = await params;
  const c = cookiesContent[locale === "en" ? "en" : "fr"];
  return buildPageMetadata({ locale, pathname: "/politique-de-cookies", title: c.title, description: c.lede });
}

export default async function CookiesPage({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  const c = cookiesContent[locale === "en" ? "en" : "fr"];
  return <LegalPage locale={locale} path="/politique-de-cookies" crumb={c.crumb} title={c.title} lede={c.lede} sections={c.sections} updatedLabel={c.updatedLabel} />;
}
