import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { getCurrentUser } from "@/lib/auth/session";
import { redirect } from "@/i18n/navigation";
import { buildPageMetadata } from "@/lib/metadata";
import { SiteConnexion } from "@/components/auth-proto/SiteConnexion";

export async function generateMetadata({ params }: { params: Promise<{ locale: string }> }): Promise<Metadata> {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: "auth.login" });
  return buildPageMetadata({ locale, pathname: "/connexion", title: t("title"), description: t("subtitle") });
}

export default async function ConnexionPage({
  params,
  searchParams,
}: {
  params: Promise<{ locale: string }>;
  searchParams: Promise<{ tab?: string }>;
}) {
  const { locale } = await params;
  const { tab } = await searchParams;

  // Déjà connecté → espace compte.
  const user = await getCurrentUser();
  if (user) redirect({ href: "/compte", locale });

  return <SiteConnexion tab={tab === "up" ? "up" : "in"} />;
}
