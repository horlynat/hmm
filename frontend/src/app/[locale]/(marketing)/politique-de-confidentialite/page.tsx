import { getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";
import { LegalPageLayout } from "@/components/sections/LegalPageLayout";

export const dynamic = "force-static";

export default async function PrivacyPolicyPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: "legal.privacy" });

  return (
    <LegalPageLayout
      title={t("title")}
      lastUpdate={t("lastUpdate")}
      intro={t("intro")}
      sections={[
        { title: t("controllerTitle"), body: <p>{t("controllerText")}</p> },
        {
          title: t("dataTitle"),
          body: (
            <ul className="list-none space-y-2 p-0">
              <li>• {t("dataContact")}</li>
              <li>• {t("dataAccount")}</li>
              <li>• {t("dataProject")}</li>
              <li>• {t("dataFreelance")}</li>
              <li>• {t("dataTechnical")}</li>
            </ul>
          ),
        },
        { title: t("purposeTitle"), body: <p>{t("purposeText")}</p> },
        { title: t("legalBasisTitle"), body: <p>{t("legalBasisText")}</p> },
        { title: t("retentionTitle"), body: <p>{t("retentionText")}</p> },
        { title: t("recipientsTitle"), body: <p>{t("recipientsText")}</p> },
        {
          title: t("rightsTitle"),
          body: (
            <>
              <p>{t("rightsText")}</p>
              <p>
                {t.rich("rightsContact", {
                  link: (chunks) => (
                    <Link
                      href="/contact"
                      className="font-semibold text-brand-primary hover:underline"
                    >
                      {chunks}
                    </Link>
                  ),
                })}
              </p>
            </>
          ),
        },
        { title: t("securityTitle"), body: <p>{t("securityText")}</p> },
        {
          title: t("cookiesTitle"),
          body: (
            <p>
              {t.rich("cookiesText", {
                link: (chunks) => (
                  <Link
                    href="/politique-de-cookies"
                    className="font-semibold text-brand-primary hover:underline"
                  >
                    {chunks}
                  </Link>
                ),
              })}
            </p>
          ),
        },
      ]}
    />
  );
}
