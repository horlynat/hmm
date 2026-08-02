import { notFound } from "next/navigation";
import { getTranslations } from "next-intl/server";
import { Badge, ButtonLink, SettingsSection, SettingsSectionGroup, Breadcrumb } from "@/components/ui";
import { getMyQuote } from "@/lib/auth/session";
import { quoteStatusVariant } from "@/lib/status";

export default async function CompteQuoteDetailPage({
  params,
}: {
  params: Promise<{ locale: string; id: string }>;
}) {
  const { locale, id } = await params;
  const quoteId = Number(id);
  if (!Number.isInteger(quoteId)) {
    notFound();
  }

  const quote = await getMyQuote(quoteId);
  if (!quote) {
    notFound();
  }

  const [t, tw] = await Promise.all([
    getTranslations({ locale, namespace: "auth.account" }),
    getTranslations({ locale, namespace: "contact.wizard" }),
  ]);

  return (
    <div className="max-w-[640px] space-y-6">
      <Breadcrumb
        items={[
          { label: t("nav.myQuotes"), href: "/compte/devis" },
          { label: quote.category },
        ]}
      />

      <ButtonLink href="/compte" variant="secondary" className="w-fit">
        {t("backLink")}
      </ButtonLink>

      <div>
        <Badge variant={quoteStatusVariant(quote.status)} className="mb-3">
          {quote.statusLabel}
        </Badge>
        <h1 className="text-[clamp(1.4rem,3vw,1.9rem)]">{quote.category}</h1>
        {quote.categoryDetail && (
          <p className="mt-1 text-sm opacity-70">
            {tw("recapCategoryDetail")}: {quote.categoryDetail}
          </p>
        )}
      </div>

      <div className="grid grid-cols-1 divide-y divide-(--border-neutral) rounded-md border border-(--border-neutral) bg-bg-card p-5 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
        {quote.budget && (
          <div className="sm:px-4 sm:first:pl-0">
            <p className="text-xs uppercase tracking-wider opacity-50">{tw("recapBudget")}</p>
            <p className="text-sm font-semibold">
              {quote.budget} {quote.currency ?? ""}
            </p>
          </div>
        )}
        {quote.timeline && (
          <div className="sm:px-4 sm:first:pl-0">
            <p className="text-xs uppercase tracking-wider opacity-50">{tw("recapDelai")}</p>
            <p className="text-sm font-semibold">{quote.timeline}</p>
          </div>
        )}
        <div className="sm:px-4 sm:first:pl-0">
          <p className="text-xs uppercase tracking-wider opacity-50">{tw("recapCanal")}</p>
          <p className="text-sm font-semibold">{quote.channel}</p>
        </div>
      </div>

      <div>
        <h2 className="mb-2 text-lg font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
          {t("quoteDetail.messageLabel")}
        </h2>
        <p className="whitespace-pre-line text-sm opacity-80">{quote.message}</p>
      </div>

      {quote.clarifications && quote.clarifications.length > 0 && (
        <div>
          <h2 className="mb-3 text-lg font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
            {t("quoteDetail.clarificationsLabel")}
          </h2>
          <SettingsSectionGroup>
            {quote.clarifications.map((entry) => (
              <SettingsSection key={entry.question} title={entry.question}>
                <p className="text-sm opacity-80">{entry.answer}</p>
              </SettingsSection>
            ))}
          </SettingsSectionGroup>
        </div>
      )}
    </div>
  );
}
