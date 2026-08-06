import { notFound } from "next/navigation";
import { getTranslations } from "next-intl/server";
import { ArrowLeft, FolderCheck, ArrowRight } from "lucide-react";
import { Badge, ButtonLink, Card, SettingsSection, SettingsSectionGroup, Breadcrumb, SectionHeading } from "@/components/ui";
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

      <ButtonLink href="/compte" variant="secondary" className="w-fit gap-1.5">
        <ArrowLeft size={15} aria-hidden="true" />
        {t("backLink")}
      </ButtonLink>

      <div>
        <div className="mb-3 flex flex-wrap items-center gap-2">
          <Badge variant={quoteStatusVariant(quote.status)}>{quote.statusLabel}</Badge>
          {quote.createdAt && (
            <span className="text-xs text-(--color-muted)">
              {t("quoteDetail.sentOnLabel")} {new Date(quote.createdAt).toLocaleDateString(locale)}
            </span>
          )}
        </div>
        <h1 className="text-[clamp(1.4rem,3vw,1.9rem)]">{quote.category}</h1>
        {quote.categoryDetail && (
          <p className="mt-1 text-sm text-(--color-muted)">
            {tw("recapCategoryDetail")}: {quote.categoryDetail}
          </p>
        )}
      </div>

      {quote.convertedProjectId && (
        <Card className="flex flex-wrap items-center justify-between gap-3 border-l-4 border-success bg-success/5 p-4">
          <div className="flex items-center gap-3">
            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-success/15 text-success">
              <FolderCheck size={17} aria-hidden="true" />
            </span>
            <p className="text-sm font-medium text-brand-dark">{t("quoteDetail.convertedBanner")}</p>
          </div>
          <ButtonLink
            href={{ pathname: "/compte/projets/[id]", params: { id: String(quote.convertedProjectId) } }}
            className="w-fit shrink-0 gap-1.5 text-xs"
          >
            {t("quoteDetail.viewProjectCta")}
            <ArrowRight size={13} aria-hidden="true" />
          </ButtonLink>
        </Card>
      )}

      <div className="grid grid-cols-1 divide-y divide-(--border-neutral) rounded-[var(--radius-lg)] border border-(--border-neutral) bg-bg-card p-5 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
        {quote.budget && (
          <div className="sm:px-4 sm:first:pl-0">
            <p className="text-xs uppercase tracking-wider text-(--color-muted)">{tw("recapBudget")}</p>
            <p className="mt-0.5 text-sm font-semibold">{quote.budget}</p>
          </div>
        )}
        {quote.timeline && (
          <div className="sm:px-4 sm:first:pl-0">
            <p className="text-xs uppercase tracking-wider text-(--color-muted)">{tw("recapDelai")}</p>
            <p className="mt-0.5 text-sm font-semibold">{quote.timeline}</p>
          </div>
        )}
        <div className="sm:px-4 sm:first:pl-0">
          <p className="text-xs uppercase tracking-wider text-(--color-muted)">{tw("recapCanal")}</p>
          <p className="mt-0.5 text-sm font-semibold">{quote.channel}</p>
        </div>
      </div>

      <div>
        <SectionHeading title={t("quoteDetail.messageLabel")} />
        <p className="whitespace-pre-line text-sm opacity-80">{quote.message}</p>
      </div>

      {quote.clarifications && quote.clarifications.length > 0 && (
        <div>
          <SectionHeading title={t("quoteDetail.clarificationsLabel")} />
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
