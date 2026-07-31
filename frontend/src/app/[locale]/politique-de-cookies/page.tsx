import { getTranslations } from "next-intl/server";
import { LegalPageLayout } from "@/components/sections/LegalPageLayout";

export const dynamic = "force-static";

function CookieRow({
  name,
  purpose,
  duration,
}: {
  name: string;
  purpose: string;
  duration: string;
}) {
  return (
    <div className="rounded-[var(--radius-md)] border border-[var(--border-soft)] bg-bg-card p-4">
      <div className="mb-1.5 font-mono text-sm font-semibold text-brand-primary">{name}</div>
      <p className="mb-1 text-sm opacity-78">{purpose}</p>
      <p className="text-xs opacity-60">{duration}</p>
    </div>
  );
}

export default async function CookiePolicyPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: "legal.cookies" });

  return (
    <LegalPageLayout
      title={t("title")}
      lastUpdate={t("lastUpdate")}
      intro={t("intro")}
      sections={[
        {
          title: t("essentialTitle"),
          body: (
            <>
              <p>{t("essentialText")}</p>
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <CookieRow
                  name="hmm_token"
                  purpose={t("sessionCookiePurpose")}
                  duration={t("sessionCookieDuration")}
                />
                <CookieRow
                  name="NEXT_LOCALE"
                  purpose={t("localeCookiePurpose")}
                  duration={t("localeCookieDuration")}
                />
              </div>
            </>
          ),
        },
        {
          title: t("localStorageTitle"),
          body: <p>{t("localStorageText")}</p>,
        },
        {
          title: t("noTrackingTitle"),
          body: <p>{t("noTrackingText")}</p>,
        },
        {
          title: t("noBannerTitle"),
          body: <p>{t("noBannerText")}</p>,
        },
        {
          title: t("controlTitle"),
          body: <p>{t("controlText")}</p>,
        },
      ]}
    />
  );
}
