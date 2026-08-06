import { getTranslations } from "next-intl/server";
import { FileText } from "lucide-react";
import { Breadcrumb, PageHeader } from "@/components/ui";
import { QuoteWizard } from "@/components/sections/QuoteWizard";
import { getCurrentUser } from "@/lib/auth/session";

export default async function NouvelleDemandeDevisPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const user = await getCurrentUser();
  if (!user) return null;

  const t = await getTranslations({ locale, namespace: "auth.account" });

  return (
    <div className="space-y-6">
      <Breadcrumb
        items={[
          { label: t("nav.myQuotes"), href: "/compte/devis" },
          { label: t("nav.newQuoteCta") },
        ]}
      />

      <PageHeader icon={FileText} title={t("newQuotePage.title")} subtitle={t("newQuotePage.subtitle")} />

      <QuoteWizard
        initialName={user.fullName ?? ""}
        initialEmail={user.email}
        successHref="/compte/devis"
        successLabel={t("newQuotePage.successCta")}
      />
    </div>
  );
}
