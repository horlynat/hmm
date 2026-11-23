import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { getAboutContent } from "@/lib/api/about-content";
import { buildPageMetadata } from "@/lib/metadata";
import { SiteAbout } from "@/components/about-proto/SiteAbout";

export const dynamic = "force-static";

export async function generateMetadata({ params }: { params: Promise<{ locale: string }> }): Promise<Metadata> {
  const { locale } = await params;
  const [t, about] = await Promise.all([getTranslations({ locale, namespace: "aboutPage" }), getAboutContent(locale)]);
  return buildPageMetadata({
    locale,
    pathname: "/a-propos",
    title: about ? `${about.heroTitle} ${about.heroTitleAccent}` : t("crumb"),
    description: about?.heroSub ?? t("pathLede"),
  });
}

export default async function AboutPage({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  return <SiteAbout locale={locale} />;
}
