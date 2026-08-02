import { getTranslations } from "next-intl/server";
import { SettingsSection, SettingsSectionGroup } from "@/components/ui";
import { getCurrentUser } from "@/lib/auth/session";

export default async function ExportPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const user = await getCurrentUser();
  if (!user) return null;

  const t = await getTranslations({ locale, namespace: "auth.profile.dataExport" });

  return (
    <div className="max-w-160 space-y-6">
      <div>
        <h1 className="mb-2 text-[clamp(1.6rem,3vw,2.2rem)]">{t("title")}</h1>
        <p className="opacity-70">{t("description")}</p>
      </div>

      <SettingsSectionGroup>
        <SettingsSection>
          <p className="mb-4 text-sm opacity-70">{t("contentHint")}</p>
          <a href="/api/me/export" download className="btn-primary w-fit">
            {t("downloadButton")}
          </a>
        </SettingsSection>
      </SettingsSectionGroup>
    </div>
  );
}
