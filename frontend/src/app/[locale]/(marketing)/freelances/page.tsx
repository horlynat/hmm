import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { buildPageMetadata } from "@/lib/metadata";
import { SiteFreelances } from "@/components/auth-proto/SiteFreelances";

export const dynamic = "force-static";

export async function generateMetadata({ params }: { params: Promise<{ locale: string }> }): Promise<Metadata> {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: "freelances" });
  return buildPageMetadata({
    locale,
    pathname: "/freelances",
    title: `${t("title")} ${t("titleAccent")}`,
    description: t("sub"),
  });
}

export default async function FreelancesPage({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  return <SiteFreelances locale={locale} />;
}
