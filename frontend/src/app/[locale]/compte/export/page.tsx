import { getTranslations } from "next-intl/server";
import { Download } from "lucide-react";
import { PageHeader, SettingsSection, SettingsSectionGroup } from "@/components/ui";
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
      <PageHeader icon={Download} title={t("title")} subtitle={t("description")} />

      <SettingsSectionGroup>
        <SettingsSection>
          <p className="mb-4 text-sm opacity-70">{t("contentHint")}</p>
          <a href="/api/me/export" download className="btn-primary w-fit gap-2">
            <Download size={16} aria-hidden="true" />
            {t("downloadButton")}
          </a>
        </SettingsSection>
      </SettingsSectionGroup>
    </div>
  );
}
