import { getTranslations } from "next-intl/server";
import { Receipt, Wallet, ArrowRight } from "lucide-react";
import { Badge, Card, ButtonLink, PageHeader } from "@/components/ui";
import { getCurrentUser } from "@/lib/auth/session";
import { invoiceStatusVariant } from "@/lib/status";
import type { SessionInvoice } from "@/lib/types";

/** Total impayé, groupé par devise — évite d'additionner des montants dans des devises différentes. */
function unpaidTotals(invoices: SessionInvoice[]): { currency: string; total: number }[] {
  const totals = new Map<string, number>();
  for (const invoice of invoices) {
    if (invoice.status !== "pending") continue;
    totals.set(invoice.currency, (totals.get(invoice.currency) ?? 0) + Number(invoice.amount));
  }
  return [...totals.entries()].map(([currency, total]) => ({ currency, total }));
}

function InvoiceCard({
  invoice,
  locale,
  labels,
}: {
  invoice: SessionInvoice;
  locale: string;
  labels: {
    dueDate: string;
    noDueDate: string;
    issuedOn: string;
    paidOn: string;
    overdue: string;
    viewProject: string;
  };
}) {
  return (
    <Card
      variant="soft"
      className={
        invoice.overdue
          ? "border-l-4 border-l-danger p-5 transition-all duration-200 hover:shadow-md"
          : "p-5 transition-all duration-200 hover:shadow-md hover:border-brand-accent/30"
      }
    >
      <div className="mb-3 flex items-start justify-between gap-2">
        <div className="min-w-0">
          <p className="text-xs font-semibold uppercase tracking-wide text-(--color-muted)">{invoice.number}</p>
          <span className="font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
            {invoice.label}
          </span>
          <p className="mt-0.5 truncate text-xs text-(--color-muted)">{invoice.projectTitle}</p>
        </div>
        <Badge variant={invoiceStatusVariant(invoice.status)}>{invoice.statusLabel}</Badge>
      </div>

      <div className="mb-4 flex items-baseline justify-between">
        <span className="text-2xl font-bold" style={{ fontFamily: "var(--font-heading)" }}>
          {invoice.formattedAmount}
        </span>
        <span className={invoice.overdue ? "text-xs font-semibold text-danger" : "text-xs text-(--color-muted)"}>
          {invoice.overdue && `${labels.overdue} — `}
          {invoice.status === "paid" && invoice.paidAt
            ? `${labels.paidOn} ${new Date(invoice.paidAt).toLocaleDateString(locale)}`
            : `${labels.dueDate}: ${invoice.dueDate ? new Date(invoice.dueDate).toLocaleDateString(locale) : labels.noDueDate}`}
        </span>
      </div>

      <ButtonLink
        href={{ pathname: "/compte/projets/[id]", params: { id: String(invoice.projectId) } }}
        variant="secondary"
        className="w-fit gap-1 text-xs"
      >
        {labels.viewProject}
        <ArrowRight size={13} aria-hidden="true" />
      </ButtonLink>
    </Card>
  );
}

export default async function FacturesPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const user = await getCurrentUser();
  if (!user) return null;

  const tf = await getTranslations({ locale, namespace: "auth.invoices" });

  const invoices = user.attributions.invoices;
  const totals = unpaidTotals(invoices);

  const labels = {
    dueDate: tf("dueDate"),
    noDueDate: tf("noDueDate"),
    issuedOn: tf("issuedOn"),
    paidOn: tf("paidOn"),
    overdue: tf("overdue"),
    viewProject: tf("viewProject"),
  };

  return (
    <div className="max-w-190 space-y-6">
      <PageHeader icon={Receipt} title={tf("title")} subtitle={tf("subtitle")} />

      {totals.length > 0 && (
        <div className="flex flex-wrap gap-3">
          {totals.map(({ currency, total }) => (
            <Card key={currency} variant="soft" className="flex items-center gap-3.5 p-4">
              <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-warning/15 text-(--color-badge-warning-text)">
                <Wallet size={19} aria-hidden="true" />
              </div>
              <div>
                <p className="text-xs font-medium text-(--color-muted)">{tf("totalUnpaid")}</p>
                <p className="text-xl font-bold leading-tight" style={{ fontFamily: "var(--font-heading)" }}>
                  {total.toLocaleString(locale, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} {currency}
                </p>
              </div>
            </Card>
          ))}
        </div>
      )}

      {invoices.length > 0 ? (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          {invoices.map((invoice) => (
            <InvoiceCard key={invoice.id} invoice={invoice} locale={locale} labels={labels} />
          ))}
        </div>
      ) : (
        <Card variant="soft" className="p-6 text-sm opacity-70">
          {tf("empty")}
        </Card>
      )}
    </div>
  );
}
