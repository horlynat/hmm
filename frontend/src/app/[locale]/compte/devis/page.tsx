import { getTranslations } from "next-intl/server";
import { FileText } from "lucide-react";
import { EmptyState, PageHeader } from "@/components/ui";
import { QuoteList } from "@/components/sections/AccountLists";
import { getCurrentUser } from "@/lib/auth/session";

export default async function CompteDevisPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const user = await getCurrentUser();
  const t = await getTranslations({ locale, namespace: "auth.account" });

  if (!user) return null;

  const { quoteRequests } = user.attributions;

  const quoteStatusLabels = {
    pending: t("quoteStatus.pending"),
    accepted: t("quoteStatus.accepted"),
    suspended: t("quoteStatus.suspended"),
    rejected: t("quoteStatus.rejected"),
  };

  return (
    <div className="space-y-6">
      <PageHeader icon={FileText} title={t("nav.myQuotes")} subtitle={t("myQuotesPage.subtitle")} />

      {quoteRequests.length > 0 ? (
        <QuoteList
          quotes={quoteRequests}
          statusLabel={t("sections.quoteBudget")}
          statusLabels={quoteStatusLabels}
        />
      ) : (
        <EmptyState icon="📝" message={t("sections.emptyQuotes")} />
      )}
    </div>
  );
}
