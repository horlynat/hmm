import { getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";
import { LegalPageLayout } from "@/components/sections/LegalPageLayout";

export const dynamic = "force-static";

export default async function LegalNoticePage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: "legal.mentions" });

  return (
    <LegalPageLayout
      title={t("title")}
      lastUpdate={t("lastUpdate")}
      sections={[
        { title: t("editorTitle"), body: <p>{t("editorText")}</p> },
        { title: t("hostingTitle"), body: <p>{t("hostingText")}</p> },
        { title: t("ipTitle"), body: <p>{t("ipText")}</p> },
        {
          title: t("moreTitle"),
          body: (
            <p>
              {t.rich("moreText", {
                privacy: (chunks) => (
                  <Link
                    href="/politique-de-confidentialite"
                    className="font-semibold text-brand-primary hover:underline"
                  >
                    {chunks}
                  </Link>
                ),
                terms: (chunks) => (
                  <Link
                    href="/conditions-generales"
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
