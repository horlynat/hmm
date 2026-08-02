import { getTranslations } from "next-intl/server";
import { SettingsSection, SettingsSectionGroup } from "@/components/ui";
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
      <div>
        <h1 className="mb-2 text-[clamp(1.6rem,3vw,2.2rem)]">{t("title")}</h1>
        <p className="opacity-70">{t("subtitle")}</p>
      </div>

      <SettingsSectionGroup>
        <SettingsSection layout="row" title={t("appearanceLabel")} description={t("appearanceHint")}>
          <ThemeToggle />
        </SettingsSection>
        <SettingsSection layout="row" title={t("languageLabel")} description={t("languageHint")}>
          <LocaleSwitcher />
        </SettingsSection>
      </SettingsSectionGroup>
    </div>
  );
}
