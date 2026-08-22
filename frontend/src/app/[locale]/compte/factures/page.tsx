import { getTranslations } from "next-intl/server";
import clsx from "clsx";
import { Receipt, Wallet, CheckCircle2, ArrowRight, MessageCircle } from "lucide-react";
import { Badge, Card, ButtonLink, EmptyState, PageHeader, Logo } from "@/components/ui";
import { InvoiceActions } from "@/components/sections/InvoiceActions";
import { getCurrentUser } from "@/lib/auth/session";
import { Link } from "@/i18n/navigation";
import { siteConfig } from "@/config/site";
import type { InvoiceStatus, SessionInvoice } from "@/lib/types";

const FILTERS: { value: InvoiceStatus | "all"; labelKey: "filterAll" | "filterPending" | "filterPaid" | "filterRevision" }[] = [
  { value: "all", labelKey: "filterAll" },
  { value: "pending", labelKey: "filterPending" },
  { value: "revision_requested", labelKey: "filterRevision" },
  { value: "paid", labelKey: "filterPaid" },
];

const STAMP_TONE: Record<InvoiceStatus, string> = {
  paid: "text-success",
  pending: "text-(--color-badge-warning-text)",
  revision_requested: "text-info",
  cancelled: "text-(--color-muted)",
};

/** Somme des montants par devise pour un statut donné — évite d'additionner des montants dans des devises différentes. */
function totalsByCurrency(invoices: SessionInvoice[], status: InvoiceStatus): { currency: string; total: number }[] {
  const totals = new Map<string, number>();
  for (const invoice of invoices) {
    if (invoice.status !== status) continue;
    totals.set(invoice.currency, (totals.get(invoice.currency) ?? 0) + Number(invoice.amount));
  }
  return [...totals.entries()].map(([currency, total]) => ({ currency, total }));
}

function SummaryCard({
  icon: Icon,
  tone,
  label,
  totals,
  locale,
}: {
  icon: typeof Wallet;
  tone: "warning" | "success";
  label: string;
  totals: { currency: string; total: number }[];
  locale: string;
}) {
  return (
    <Card variant="soft" className="flex items-center gap-3.5 p-4">
      <div
        className={clsx(
          "flex h-11 w-11 shrink-0 items-center justify-center rounded-xl",
          tone === "warning" ? "bg-warning/15 text-(--color-badge-warning-text)" : "bg-success/15 text-success",
        )}
      >
        <Icon size={19} aria-hidden="true" />
      </div>
      <div className="min-w-0">
        <p className="text-xs font-medium text-(--color-muted)">{label}</p>
        {totals.length > 0 ? (
          totals.map(({ currency, total }) => (
            <p key={currency} className="truncate text-xl font-bold leading-tight" style={{ fontFamily: "var(--font-heading)" }}>
              {total.toLocaleString(locale, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} {currency}
            </p>
          ))
        ) : (
          <p className="text-xl font-bold leading-tight text-(--color-muted)">—</p>
        )}
      </div>
    </Card>
  );
}

interface InvoiceLabels {
  documentTitle: string;
  billedTo: string;
  descriptionLabel: string;
  amountLabel: string;
  totalLabel: string;
  projectRefLabel: string;
  qtyLabel: string;
  unitPriceLabel: string;
  dueDate: string;
  noDueDate: string;
  issuedOn: string;
  paidOn: string;
  overdue: string;
  viewProject: string;
  contactNote: string;
  contactCta: string;
}

/**
 * Rendu façon vraie facture professionnelle : en-tête émetteur/document,
 * bloc facturé-à + métadonnées, tableau de prestation, total, tampon de
 * statut en filigrane. Les actions client (valider/réviser) restent
 * visuellement détachées du corps du document, dans un pied de page distinct.
 */
function InvoiceDocument({
  invoice,
  locale,
  labels,
  clientName,
  clientEmail,
}: {
  invoice: SessionInvoice;
  locale: string;
  labels: InvoiceLabels;
  clientName: string;
  clientEmail: string;
}) {
  const stampLabel = invoice.overdue ? labels.overdue : invoice.statusLabel;
  const stampTone = invoice.overdue ? "text-danger" : STAMP_TONE[invoice.status];

  return (
    <Card
      variant="soft"
      className={clsx(
        "relative overflow-hidden p-0 transition-shadow duration-200 hover:shadow-md",
        invoice.overdue && "border-l-4 border-l-danger",
      )}
    >
      {/* Tampon de statut en filigrane — signal visuel immédiat, comme sur un vrai document. */}
      <span
        aria-hidden="true"
        className={clsx(
          "pointer-events-none absolute -right-6 top-8 rotate-[18deg] select-none whitespace-nowrap text-3xl font-black uppercase tracking-widest opacity-[0.12] sm:text-4xl",
          stampTone,
        )}
        style={{ fontFamily: "var(--font-heading)" }}
      >
        {stampLabel}
      </span>

      <div className="relative space-y-6 p-5 sm:p-7">
        {/* En-tête : émetteur à gauche, document à droite */}
        <div className="flex flex-col gap-4 border-b border-(--border-neutral) pb-5 sm:flex-row sm:items-start sm:justify-between">
          <div className="flex items-center gap-3">
            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-sm bg-brand-primary text-(--color-on-brand-primary)">
              <Logo className="h-5 w-5" />
            </span>
            <div className="min-w-0">
              <p className="truncate font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
                {siteConfig.name}
              </p>
              <p className="text-xs text-(--color-muted)">Brazzaville, République du Congo</p>
            </div>
          </div>
          <div className="sm:text-right">
            <p className="text-xl font-bold uppercase tracking-wide" style={{ fontFamily: "var(--font-heading)" }}>
              {labels.documentTitle}
            </p>
            <p className="font-mono text-sm text-(--color-muted)">{invoice.number}</p>
          </div>
        </div>

        {/* Facturé à + métadonnées */}
        <div className="grid grid-cols-1 gap-5 text-sm sm:grid-cols-2">
          <div className="min-w-0">
            <p className="mb-1 text-xs font-semibold uppercase tracking-wide text-(--color-muted)">{labels.billedTo}</p>
            <p className="truncate font-semibold text-brand-dark">{clientName}</p>
            <p className="truncate text-(--color-muted)">{clientEmail}</p>
          </div>
          <div className="min-w-0">
            <dl className="grid w-fit grid-cols-[auto_auto] gap-x-3 gap-y-1 sm:ml-auto">
              <dt className="text-(--color-muted)">{labels.issuedOn}</dt>
              <dd className="text-right font-medium text-brand-dark">{new Date(invoice.issuedAt).toLocaleDateString(locale)}</dd>
              <dt className="text-(--color-muted)">{labels.dueDate}</dt>
              <dd className={clsx("text-right font-medium", invoice.overdue ? "font-semibold text-danger" : "text-brand-dark")}>
                {invoice.status === "paid" && invoice.paidAt
                  ? `${labels.paidOn} ${new Date(invoice.paidAt).toLocaleDateString(locale)}`
                  : invoice.dueDate
                    ? new Date(invoice.dueDate).toLocaleDateString(locale)
                    : labels.noDueDate}
              </dd>
            </dl>
            <p className="mt-1 truncate text-(--color-muted) sm:text-right">
              {labels.projectRefLabel} : <span className="font-medium text-brand-dark">{invoice.projectTitle}</span>
            </p>
          </div>
        </div>

        {/* Tableau de prestation */}
        <div className="overflow-hidden rounded-[var(--radius-md)] border border-(--border-neutral)">
          <table className="w-full border-collapse text-sm">
            <thead>
              <tr className="bg-(--color-surface-muted) text-left text-xs font-semibold uppercase tracking-wide text-(--color-muted)">
                <th scope="col" className="px-3.5 py-2.5 sm:px-4">
                  {labels.descriptionLabel}
                </th>
                <th scope="col" className="px-3.5 py-2.5 text-right sm:px-4">
                  {labels.amountLabel}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr className="border-t border-(--border-neutral)">
                <td className="px-3.5 py-3.5 align-top sm:px-4">
                  <p className="font-medium text-brand-dark">{invoice.label}</p>
                  <p className="mt-1 text-xs text-(--color-muted)">
                    {labels.qtyLabel} : 1 &times; {labels.unitPriceLabel} : {invoice.formattedConvertedAmount}
                  </p>
                </td>
                <td className="whitespace-nowrap px-3.5 py-3.5 text-right align-top font-medium text-brand-dark sm:px-4">
                  {invoice.formattedConvertedAmount}
                </td>
              </tr>
            </tbody>
            <tfoot>
              <tr className="border-t-2 border-(--border-neutral)">
                <td className="px-3.5 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-(--color-muted) sm:px-4">
                  {labels.totalLabel}
                </td>
                <td className="whitespace-nowrap px-3.5 py-3.5 text-right sm:px-4">
                  <span className="text-lg font-bold sm:text-xl" style={{ fontFamily: "var(--font-heading)" }}>
                    {invoice.formattedConvertedAmount}
                  </span>
                </td>
              </tr>
            </tfoot>
          </table>
        </div>

        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-dashed border-(--border-neutral) pt-4">
          <ButtonLink
            href={{ pathname: "/compte/projets/[id]", params: { id: String(invoice.projectId) } }}
            variant="secondary"
            className="w-fit gap-1 text-xs"
          >
            {labels.viewProject}
            <ArrowRight size={13} aria-hidden="true" />
          </ButtonLink>
          <a
            href={siteConfig.whatsappUrl}
            target="_blank"
            rel="noopener noreferrer"
            className="flex items-center gap-1.5 text-xs font-medium text-(--color-muted) hover:text-brand-primary"
          >
            <MessageCircle size={13} aria-hidden="true" />
            {labels.contactNote} {labels.contactCta}
          </a>
        </div>
      </div>

      <div className="border-t border-(--border-neutral) bg-(--color-surface-muted) px-5 py-4 sm:px-7">
        <InvoiceActions invoice={invoice} locale={locale} />
      </div>
    </Card>
  );
}

export default async function FacturesPage({
  params,
  searchParams,
}: {
  params: Promise<{ locale: string }>;
  searchParams: Promise<{ status?: string }>;
}) {
  const { locale } = await params;
  const { status } = await searchParams;
  const user = await getCurrentUser();
  if (!user) return null;

  const tf = await getTranslations({ locale, namespace: "auth.invoices" });

  const invoices = user.attributions.invoices;
  const unpaidTotals = totalsByCurrency(invoices, "pending");
  const paidTotals = totalsByCurrency(invoices, "paid");

  const activeFilter = FILTERS.some((f) => f.value === status) ? (status as InvoiceStatus | "all") : "all";
  const filteredInvoices = activeFilter === "all" ? invoices : invoices.filter((inv) => inv.status === activeFilter);

  const labels: InvoiceLabels = {
    documentTitle: tf("documentTitle"),
    billedTo: tf("billedTo"),
    descriptionLabel: tf("descriptionLabel"),
    amountLabel: tf("amountLabel"),
    totalLabel: tf("totalLabel"),
    projectRefLabel: tf("projectRefLabel"),
    qtyLabel: tf("qtyLabel"),
    unitPriceLabel: tf("unitPriceLabel"),
    dueDate: tf("dueDate"),
    noDueDate: tf("noDueDate"),
    issuedOn: tf("issuedOn"),
    paidOn: tf("paidOn"),
    overdue: tf("overdue"),
    viewProject: tf("viewProject"),
    contactNote: tf("contactNote"),
    contactCta: tf("contactCta"),
  };

  return (
    <div className="max-w-190 space-y-6">
      <PageHeader
        icon={Receipt}
        title={tf("title")}
        subtitle={tf("subtitle")}
        actions={<Badge variant="neutral">{tf("statCount", { count: invoices.length })}</Badge>}
      />

      {invoices.length > 0 && (
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <SummaryCard icon={Wallet} tone="warning" label={tf("totalUnpaid")} totals={unpaidTotals} locale={locale} />
          <SummaryCard icon={CheckCircle2} tone="success" label={tf("statTotalPaid")} totals={paidTotals} locale={locale} />
        </div>
      )}

      {invoices.length > 0 && (
        <nav aria-label={tf("title")} className="flex flex-wrap gap-2">
          {FILTERS.map((filter) => {
            const active = filter.value === activeFilter;
            return (
              <Link
                key={filter.value}
                href={filter.value === "all" ? "/compte/factures" : { pathname: "/compte/factures", query: { status: filter.value } }}
                className={clsx(
                  "rounded-full border px-3.5 py-2 font-mono text-xs transition-colors",
                  active
                    ? "border-brand-primary bg-brand-primary text-(--color-on-brand-primary)"
                    : "border-(--border-neutral) bg-bg-card text-(--color-muted) hover:text-brand-primary",
                )}
              >
                {tf(filter.labelKey)}
              </Link>
            );
          })}
        </nav>
      )}

      {invoices.length === 0 ? (
        <EmptyState icon={Receipt} message={tf("empty")} />
      ) : filteredInvoices.length === 0 ? (
        <EmptyState icon={Receipt} message={tf("emptyFiltered")} />
      ) : (
        <div className="grid grid-cols-1 gap-5 xl:grid-cols-2">
          {filteredInvoices.map((invoice) => (
            <InvoiceDocument
              key={invoice.id}
              invoice={invoice}
              locale={locale}
              labels={labels}
              clientName={user.fullName ?? user.email}
              clientEmail={user.email}
            />
          ))}
        </div>
      )}
    </div>
  );
}
