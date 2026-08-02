import { getTranslations } from "next-intl/server";
import { DeleteAccountSection } from "@/components/sections/DeleteAccountSection";
import { SettingsSection, SettingsSectionGroup } from "@/components/ui";
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
      <div>
        <h1 className="mb-2 text-[clamp(1.6rem,3vw,2.2rem)] text-danger">{t("title")}</h1>
        <p className="opacity-70">{t("description")}</p>
      </div>

      <SettingsSectionGroup>
        <SettingsSection tone="danger">
          <DeleteAccountSection />
        </SettingsSection>
      </SettingsSectionGroup>
    </div>
  );
}
