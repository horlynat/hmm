import { getTranslations } from "next-intl/server";
import { EmptyState } from "@/components/ui";
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
      <div>
        <h1 className="mb-2 text-[clamp(1.6rem,3vw,2.2rem)]">{t("nav.myQuotes")}</h1>
        <p className="opacity-70">{t("myQuotesPage.subtitle")}</p>
      </div>

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
