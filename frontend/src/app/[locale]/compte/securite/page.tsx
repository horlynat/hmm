import { getTranslations } from "next-intl/server";
import { Badge, SettingsSection, SettingsSectionGroup } from "@/components/ui";
import { ResendVerificationButton } from "@/components/sections/ResendVerificationButton";
import { getCurrentUser } from "@/lib/auth/session";

export default async function SecuritePage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const user = await getCurrentUser();
  if (!user) return null;

  const t = await getTranslations({ locale, namespace: "auth.profile.security" });

  const hasLoginInfo = user.lastLoginAt || user.lastIp || user.lastLocation || user.lastDevice;

  return (
    <div className="max-w-160 space-y-6">
      <div>
        <h1 className="mb-2 text-[clamp(1.6rem,3vw,2.2rem)]">{t("title")}</h1>
        <p className="opacity-70">{t("subtitle")}</p>
      </div>

      <SettingsSectionGroup>
        <SettingsSection title={t("emailGroupTitle")}>
          <div className="mb-3 flex flex-wrap items-center gap-2">
            <Badge variant="neutral">
              {user.isVerified ? t("emailVerified") : t("emailUnverified")}
            </Badge>
          </div>
          {!user.isVerified && <ResendVerificationButton />}
        </SettingsSection>

        <SettingsSection title={t("twoFactorGroupTitle")} description={t("twoFactorHint")}>
          <Badge variant="neutral">
            {user.isTwoFactorEnabled ? t("twoFactorEnabled") : t("twoFactorDisabled")}
          </Badge>
        </SettingsSection>

        {hasLoginInfo && (
          <SettingsSection title={t("lastLoginGroupTitle")}>
            <dl className="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
              {user.lastLoginAt && (
                <div>
                  <dt className="text-xs opacity-50">{t("lastLoginAtLabel")}</dt>
                  <dd className="font-semibold">{new Date(user.lastLoginAt).toLocaleString(locale)}</dd>
                </div>
              )}
              {user.lastLocation && (
                <div>
                  <dt className="text-xs opacity-50">{t("lastLocationLabel")}</dt>
                  <dd className="font-semibold">{user.lastLocation}</dd>
                </div>
              )}
              {user.lastDevice && (
                <div>
                  <dt className="text-xs opacity-50">{t("lastDeviceLabel")}</dt>
                  <dd className="font-semibold">{user.lastDevice}</dd>
                </div>
              )}
              {user.lastIp && (
                <div>
                  <dt className="text-xs opacity-50">{t("lastIpLabel")}</dt>
                  <dd className="font-mono text-xs font-semibold">{user.lastIp}</dd>
                </div>
              )}
            </dl>
          </SettingsSection>
        )}
      </SettingsSectionGroup>
    </div>
  );
}
