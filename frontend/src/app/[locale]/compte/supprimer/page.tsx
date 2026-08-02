import { getTranslations } from "next-intl/server";
import { TriangleAlert } from "lucide-react";
import { DeleteAccountSection } from "@/components/sections/DeleteAccountSection";
import { PageHeader, SettingsSection, SettingsSectionGroup } from "@/components/ui";
import { redirect } from "@/i18n/navigation";
import { getCurrentUser } from "@/lib/auth/session";

export default async function SupprimerPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const user = await getCurrentUser();
  if (!user) return null;

  // Un compte staff ne peut pas s'auto-supprimer depuis cet espace (cf. MeController::delete()).
  if (user.isCollaborator) {
    redirect({ href: "/compte/profil", locale });
  }

  const t = await getTranslations({ locale, namespace: "auth.profile.dangerZone" });

  return (
    <div className="max-w-160 space-y-6">
      <PageHeader icon={TriangleAlert} title={t("title")} subtitle={t("description")} tone="danger" />

      <SettingsSectionGroup>
        <SettingsSection tone="danger">
          <DeleteAccountSection />
        </SettingsSection>
      </SettingsSectionGroup>
    </div>
  );
}
