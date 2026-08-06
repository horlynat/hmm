import { getTranslations } from "next-intl/server";
import { Palette, Languages, Settings } from "lucide-react";
import { PageHeader, SettingsSection, SettingsSectionGroup } from "@/components/ui";
import { ThemeToggle, LocaleSwitcher } from "@/components/layout";
import { getCurrentUser } from "@/lib/auth/session";

export default async function ParametresPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const user = await getCurrentUser();
  if (!user) return null;

  const t = await getTranslations({ locale, namespace: "auth.settings" });

  return (
    <div className="max-w-160 space-y-6">
      <PageHeader icon={Settings} title={t("title")} subtitle={t("subtitle")} />

      <SettingsSectionGroup>
        <SettingsSection layout="row" icon={Palette} title={t("appearanceLabel")} description={t("appearanceHint")}>
          <ThemeToggle />
        </SettingsSection>
        <SettingsSection layout="row" icon={Languages} title={t("languageLabel")} description={t("languageHint")}>
          <LocaleSwitcher />
        </SettingsSection>
      </SettingsSectionGroup>
    </div>
  );
}
