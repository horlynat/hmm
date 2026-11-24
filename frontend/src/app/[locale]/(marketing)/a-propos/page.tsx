import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { getAboutContent } from "@/lib/api/about-content";
import { buildPageMetadata } from "@/lib/metadata";
import { SiteAbout } from "@/components/about-proto/SiteAbout";

export const dynamic = "force-static";
// Le statut « à venir » d'une formation (SiteAbout) compare une date de
// début à l'heure courante : sans borne de validité, la page générée
// statiquement figerait cette comparaison au moment du build et resterait
// fausse jusqu'à la prochaine revalidation par tag (aucun tag ne change au
// fil du temps qui passe). Revalidation horaire, comme les autres contenus
// éditoriaux peu volatils de ce site.
export const revalidate = 3600;

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
