import { getTranslations } from "next-intl/server";
import { LegalPageLayout } from "@/components/sections/LegalPageLayout";

export const dynamic = "force-static";

export default async function TermsPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: "legal.terms" });

  return (
    <LegalPageLayout
      title={t("title")}
      lastUpdate={t("lastUpdate")}
      intro={t("intro")}
      sections={[
        { title: t("objectTitle"), body: <p>{t("objectText")}</p> },
        { title: t("accessTitle"), body: <p>{t("accessText")}</p> },
        { title: t("accountsTitle"), body: <p>{t("accountsText")}</p> },
        { title: t("contentTitle"), body: <p>{t("contentText")}</p> },
        { title: t("aiTitle"), body: <p>{t("aiText")}</p> },
        { title: t("liabilityTitle"), body: <p>{t("liabilityText")}</p> },
        { title: t("lawTitle"), body: <p>{t("lawText")}</p> },
        { title: t("changesTitle"), body: <p>{t("changesText")}</p> },
      ]}
    />
  );
}
