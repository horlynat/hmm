import { getTranslations } from "next-intl/server";
import { FolderKanban } from "lucide-react";
import { PageHeader } from "@/components/ui";
import { MyProjectsPanel } from "@/components/sections/MyProjectsPanel";
import { getCurrentUser } from "@/lib/auth/session";

/**
 * Réservée aux non-collaborateurs dans la navigation (cf. AccountNav.tsx) :
 * un collaborateur voit l'équivalent — et plus, avec "Projets disponibles" et
 * "Mes demandes" — dans l'onglet "Mes projets" du hub /compte/gestion-projet.
 * La route reste accessible directement (lien historique, favori) et
 * fonctionne pour tout le monde : le corps est le même composant partagé
 * (MyProjectsPanel) que celui de l'onglet.
 */
export default async function ComptProjetsPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const user = await getCurrentUser();
  const t = await getTranslations({ locale, namespace: "auth.account" });

  if (!user) return null;

  return (
    <div className="space-y-8">
      <PageHeader icon={FolderKanban} title={t("nav.myProjects")} subtitle={t("myProjectsPage.subtitle")} />
      <MyProjectsPanel user={user} locale={locale} />
    </div>
  );
}
