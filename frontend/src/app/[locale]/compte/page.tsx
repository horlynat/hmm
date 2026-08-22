import type { ComponentProps } from "react";
import { getTranslations } from "next-intl/server";
import clsx from "clsx";
import { FolderKanban, FileText, Receipt, Briefcase, History, MessageSquare } from "lucide-react";
import { Badge, Card, EmptyState, StatCard } from "@/components/ui";
import { WelcomeBanner } from "@/components/sections/WelcomeBanner";
import { getCurrentUser, getMyActivity } from "@/lib/auth/session";
import { Link } from "@/i18n/navigation";
import { invoiceStatusVariant } from "@/lib/status";
import type { SessionProject, SessionActivityEntry, SessionComment, SessionInvoice } from "@/lib/types";

const PREVIEW_COUNT = 4;
const UPCOMING_COUNT = 3;
const FEED_COUNT = 3;

/**
 * Ni `SessionProject` ni `SessionQuote` ne portent de date de création/mise à
 * jour côté API (`/api/me` ne les expose pas — cf. MeController) : impossible
 * de construire un vrai flux d'activité récente sans changement backend. En
 * attendant, cette section reste 100% honnête en s'appuyant sur la seule date
 * réellement disponible : l'échéance des projets.
 */
function upcomingDeadlines(projects: SessionProject[], limit: number): SessionProject[] {
  const seen = new Set<number>();
  return projects
    .filter((p) => p.deadline && p.status !== "termine" && !seen.has(p.id) && seen.add(p.id))
    .sort((a, b) => new Date(a.deadline!).getTime() - new Date(b.deadline!).getTime())
    .slice(0, limit);
}

function activeProjectsOf(projects: SessionProject[]): SessionProject[] {
  const seen = new Set<number>();
  return projects.filter((p) => p.status === "en_cours" && !seen.has(p.id) && seen.add(p.id));
}

/**
 * Anneau de progression — moyenne d'avancement des projets actifs. Seule
 * visualisation du dashboard jusqu'ici réduit à des compteurs statiques ;
 * l'info existe déjà par projet (SessionProject.progress) mais n'était
 * agrégée nulle part au niveau du portefeuille.
 */
/**
 * Anneau miniature embarqué dans une tuile de stat (mockup validé : `.kpi-ring`,
 * 40px, sans texte superposé — la valeur brute et le libellé de progression
 * vivent à côté, pas dans l'anneau). Remplace l'ancien grand anneau de 116px
 * qui trônait dans un bandeau héro à part : ici c'est une tuile de plus,
 * pas l'élément dominant de la page.
 */
function ActiveProjectsTile({ count, avgProgress, label, caption }: { count: number; avgProgress: number; label: string; caption: string }) {
  const size = 34;
  const stroke = 5;
  const radius = (size - stroke) / 2;
  const circumference = 2 * Math.PI * radius;
  const offset = circumference * (1 - avgProgress / 100);

  return (
    <Card variant="soft" className="p-3">
      <div className="mb-1 text-[11px] font-bold uppercase tracking-wide text-(--color-muted)">{label}</div>
      <div className="flex items-center gap-2.5">
        <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`} className="-rotate-90 shrink-0">
          <circle cx={size / 2} cy={size / 2} r={radius} strokeWidth={stroke} fill="none" className="stroke-(--border-neutral)" />
          <circle
            cx={size / 2}
            cy={size / 2}
            r={radius}
            strokeWidth={stroke}
            fill="none"
            strokeLinecap="round"
            strokeDasharray={circumference}
            strokeDashoffset={offset}
            className="stroke-brand-primary"
          />
        </svg>
        <div className="min-w-0">
          <div className="text-xl font-extrabold leading-none" style={{ fontFamily: "var(--font-heading)" }}>
            {count}
          </div>
          <div className="mt-1 truncate text-[10.5px] font-semibold text-(--color-muted)">{caption}</div>
        </div>
      </div>
    </Card>
  );
}

interface MonthPoint {
  label: string;
  total: number;
}

/**
 * Total facturé (converti dans la devise d'affichage) par mois calendaire,
 * sur les `months` derniers mois glissants — uniquement les factures dont la
 * conversion a réussi (`convertedAmount` non nul) : mélanger un montant
 * resté dans sa devise d'origine avec des montants convertis fausserait la
 * somme, même précaution que `totalsByCurrency` dans compte/factures/page.tsx.
 */
function monthlyInvoiceTotals(invoices: SessionInvoice[], months: number, locale: string): MonthPoint[] {
  const now = new Date();
  const points: MonthPoint[] = [];
  for (let i = months - 1; i >= 0; i--) {
    const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
    points.push({ label: d.toLocaleDateString(locale, { month: "short" }), total: 0 });
  }
  for (const invoice of invoices) {
    if (invoice.convertedAmount === null) continue;
    const issued = new Date(invoice.issuedAt);
    const monthsAgo = (now.getFullYear() - issued.getFullYear()) * 12 + (now.getMonth() - issued.getMonth());
    if (monthsAgo < 0 || monthsAgo >= months) continue;
    points[months - 1 - monthsAgo].total += Number(invoice.convertedAmount);
  }
  return points;
}

/** Courbe (aire) du facturé mensuel — le dashboard n'affichait jusqu'ici les factures qu'en liste plate, sans aucune mise en perspective dans le temps. */
function RevenueChart({ points, label }: { points: MonthPoint[]; label: string }) {
  const width = 280;
  const height = 96;
  const max = Math.max(...points.map((p) => p.total), 1);
  const stepX = width / (points.length - 1 || 1);
  const coords = points.map((p, i) => ({ x: i * stepX, y: height - (p.total / max) * (height - 12) - 4 }));
  const linePath = coords.map((c, i) => `${i === 0 ? "M" : "L"}${c.x.toFixed(1)},${c.y.toFixed(1)}`).join(" ");
  const areaPath = `${linePath} L${width},${height} L0,${height} Z`;

  return (
    <div>
      <p className="mb-3 text-xs font-bold uppercase tracking-wider text-(--color-muted)">{label}</p>
      <svg viewBox={`0 0 ${width} ${height}`} className="w-full" role="img" aria-label={label}>
        <defs>
          <linearGradient id="revenue-fill" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stopColor="var(--color-brand-primary)" stopOpacity={0.22} />
            <stop offset="100%" stopColor="var(--color-brand-primary)" stopOpacity={0} />
          </linearGradient>
        </defs>
        <path d={areaPath} fill="url(#revenue-fill)" stroke="none" />
        <path
          d={linePath}
          fill="none"
          className="stroke-brand-primary"
          strokeWidth={2}
          strokeLinejoin="round"
          strokeLinecap="round"
        />
      </svg>
      <div className="mt-1 flex justify-between text-[10px] text-(--color-muted)">
        {points.map((p) => (
          <span key={p.label}>{p.label}</span>
        ))}
      </div>
    </div>
  );
}

interface StatusSlice {
  status: string;
  label: string;
  count: number;
  colorClass: string;
}

const STATUS_COLOR_CLASS: Record<string, string> = {
  a_venir: "stroke-info",
  en_cours: "stroke-success",
  collaboration: "stroke-brand-accent",
  suspendu: "stroke-warning",
  termine: "stroke-(--color-muted)",
};

/** Répartition des projets par statut, dédoublonnée par id (un projet où l'on est à la fois propriétaire et collaborateur ne doit compter qu'une fois). */
function projectsByStatus(projects: SessionProject[]): StatusSlice[] {
  const seen = new Set<number>();
  const counts = new Map<string, { label: string; count: number }>();
  for (const p of projects) {
    if (seen.has(p.id)) continue;
    seen.add(p.id);
    const entry = counts.get(p.status) ?? { label: p.statusLabel, count: 0 };
    entry.count += 1;
    counts.set(p.status, entry);
  }
  return [...counts.entries()]
    .map(([status, { label, count }]) => ({
      status,
      label,
      count,
      colorClass: STATUS_COLOR_CLASS[status] ?? "stroke-(--color-muted)",
    }))
    .sort((a, b) => b.count - a.count);
}

/** Anneau segmenté (donut) — un coup d'œil sur la répartition du portefeuille par statut, absent jusqu'ici : seuls les projets actifs individuels étaient listés, jamais l'ensemble résumé. */
function StatusDonut({ slices, label }: { slices: StatusSlice[]; label: string }) {
  const total = slices.reduce((sum, s) => sum + s.count, 0);
  const size = 96;
  const stroke = 14;
  const radius = (size - stroke) / 2;
  const circumference = 2 * Math.PI * radius;

  // Offsets cumulés calculés à part (pas dans le .map() qui produit le JSX,
  // pour ne pas muter une variable capturée pendant le rendu — react-hooks/immutability).
  const segments: { slice: StatusSlice; dash: number; offset: number }[] = [];
  let cumulative = 0;
  for (const s of slices) {
    const fraction = total > 0 ? s.count / total : 0;
    segments.push({ slice: s, dash: fraction * circumference, offset: -cumulative * circumference });
    cumulative += fraction;
  }

  return (
    <div>
      <p className="mb-3 text-xs font-bold uppercase tracking-wider text-(--color-muted)">{label}</p>
      <div className="flex items-center gap-4">
        <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`} className="-rotate-90 shrink-0">
          <circle cx={size / 2} cy={size / 2} r={radius} strokeWidth={stroke} fill="none" className="stroke-(--border-neutral)" />
          {segments.map(({ slice: s, dash, offset }) => (
            <circle
              key={s.status}
              cx={size / 2}
              cy={size / 2}
              r={radius}
              strokeWidth={stroke}
              fill="none"
              strokeDasharray={`${dash} ${circumference - dash}`}
              strokeDashoffset={offset}
              className={s.colorClass}
            />
          ))}
        </svg>
        <ul className="min-w-0 flex-1 space-y-1.5 text-xs">
          {slices.map((s) => (
            <li key={s.status} className="flex items-center gap-2">
              <span className={clsx("h-2 w-2 shrink-0 rounded-full", s.colorClass.replace("stroke-", "bg-"))} />
              <span className="min-w-0 flex-1 truncate text-(--color-muted)">{s.label}</span>
              <span className="font-semibold text-(--brand-dark)">{s.count}</span>
            </li>
          ))}
        </ul>
      </div>
    </div>
  );
}

function timeAgoLabel(iso: string, locale: string): string {
  return new Date(iso).toLocaleDateString(locale, {
    day: "numeric",
    month: "short",
    hour: "2-digit",
    minute: "2-digit",
  });
}

/**
 * Titre de widget + lien "Tout voir" optionnel, à l'intérieur de la carte
 * (mockup validé : `.card-title`, pas un `<SectionHeading>` externe à la
 * carte) — partagé par tous les widgets de la ligne "Activité" et
 * "Projets & facturation" pour un traitement uniforme.
 */
function CardHeader({
  label,
  viewAllHref,
  viewAllLabel,
}: {
  label: string;
  viewAllHref?: ComponentProps<typeof Link>["href"];
  viewAllLabel?: string;
}) {
  return (
    <div className="mb-3 flex items-center justify-between gap-2">
      <p className="text-xs font-bold uppercase tracking-wider text-(--color-muted)">{label}</p>
      {viewAllHref && (
        <Link href={viewAllHref} className="shrink-0 text-xs font-semibold text-brand-primary hover:underline">
          {viewAllLabel}
        </Link>
      )}
    </div>
  );
}

function ActivityFeed({
  entries,
  locale,
  label,
  emptyMessage,
}: {
  entries: SessionActivityEntry[];
  locale: string;
  label: string;
  emptyMessage: string;
}) {
  return (
    <Card variant="soft" className="p-4">
      <CardHeader label={label} />
      {entries.length === 0 ? (
        <EmptyState icon={History} message={emptyMessage} />
      ) : (
        <ol className="space-y-4">
          {entries.map((entry, i) => (
            <li key={entry.id} className="relative flex gap-3">
              {i < entries.length - 1 && (
                <span
                  aria-hidden="true"
                  className="absolute top-8 left-4 h-[calc(100%-1rem)] w-px -translate-x-1/2 bg-(--border-neutral)"
                />
              )}
              <span className="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-(--color-surface-muted) text-brand-primary">
                <History size={14} aria-hidden="true" />
              </span>
              <Link
                href={{ pathname: "/compte/projets/[id]", params: { id: String(entry.projectId) } }}
                className="min-w-0 flex-1 rounded-lg px-2 py-1 -my-1 transition-colors hover:bg-(--color-surface-muted)"
              >
                <p className="text-sm">
                  <span className="font-semibold">{entry.actionLabel}</span>{" "}
                  <span className="text-(--color-muted)">— {entry.projectTitle}</span>
                </p>
                <p className="mt-0.5 text-xs text-(--color-muted)">{timeAgoLabel(entry.createdAt, locale)}</p>
              </Link>
            </li>
          ))}
        </ol>
      )}
    </Card>
  );
}

function MessagesFeed({
  messages,
  locale,
  label,
  emptyMessage,
  youLabel,
}: {
  messages: SessionComment[];
  locale: string;
  label: string;
  emptyMessage: string;
  youLabel: string;
}) {
  return (
    <Card variant="soft" className="p-4">
      <CardHeader label={label} />
      {messages.length === 0 ? (
        <EmptyState icon={MessageSquare} message={emptyMessage} />
      ) : (
        <ol className="space-y-4">
          {messages.map((message, i) => (
            <li key={message.id} className="relative flex gap-3">
              {i < messages.length - 1 && (
                <span
                  aria-hidden="true"
                  className="absolute top-8 left-4 h-[calc(100%-1rem)] w-px -translate-x-1/2 bg-(--border-neutral)"
                />
              )}
              <span className="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-(--color-surface-muted) text-brand-primary">
                <MessageSquare size={14} aria-hidden="true" />
              </span>
              <Link
                href={{ pathname: "/compte/projets/[id]", params: { id: String(message.projectId) } }}
                className="min-w-0 flex-1 rounded-lg px-2 py-1 -my-1 transition-colors hover:bg-(--color-surface-muted)"
              >
                <p className="text-sm">
                  <span className="font-semibold">{message.isMine ? youLabel : (message.author.fullName ?? message.author.email)}</span>{" "}
                  <span className="text-(--color-muted)">— {message.projectTitle}</span>
                </p>
                <p className="mt-0.5 truncate text-sm text-(--color-muted)">{message.content}</p>
                <p className="mt-0.5 text-xs text-(--color-muted)">{timeAgoLabel(message.createdAt, locale)}</p>
              </Link>
            </li>
          ))}
        </ol>
      )}
    </Card>
  );
}

/** Échéances à venir — troisième carte de la ligne "Aperçu" (mockup validé), plus une section pleine largeur à part. */
function UpcomingDeadlinesCard({
  projects,
  locale,
  label,
  emptyMessage,
  overdueLabel,
}: {
  projects: SessionProject[];
  locale: string;
  label: string;
  emptyMessage: string;
  overdueLabel: string;
}) {
  return (
    <Card variant="soft" className="p-4">
      <CardHeader label={label} />
      {projects.length === 0 ? (
        <EmptyState icon={FolderKanban} message={emptyMessage} />
      ) : (
        <ol className="space-y-3">
          {projects.map((project) => {
            const overdue = new Date(project.deadline!) < new Date();
            return (
              <li key={project.id}>
                <Link
                  href={{ pathname: "/compte/projets/[id]", params: { id: String(project.id) } }}
                  className="flex items-center gap-3 rounded-lg px-1 py-0.5 -mx-1 transition-colors hover:bg-(--color-surface-muted)"
                >
                  <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-(--color-surface-muted) text-brand-primary">
                    <FolderKanban size={14} aria-hidden="true" />
                  </span>
                  <span className="min-w-0 flex-1">
                    <span className="block truncate text-sm font-semibold">{project.title}</span>
                    <span className="block text-xs text-(--color-muted)">{project.statusLabel}</span>
                  </span>
                  <span className={clsx("shrink-0 text-xs font-semibold", overdue ? "text-danger" : "text-(--color-muted)")}>
                    {overdue ? overdueLabel : new Date(project.deadline!).toLocaleDateString(locale, { day: "numeric", month: "short" })}
                  </span>
                </Link>
              </li>
            );
          })}
        </ol>
      )}
    </Card>
  );
}

/** Barres d'avancement des projets en cours — troisième ligne du dashboard (mockup validé), absent jusqu'ici : l'avancement n'existait qu'en donut agrégé ou sur la fiche de chaque projet. */
function InProgressProjects({
  projects,
  label,
  emptyMessage,
  progressLabel,
  viewAllHref,
  viewAllLabel,
}: {
  projects: SessionProject[];
  label: string;
  emptyMessage: string;
  progressLabel: string;
  viewAllHref?: ComponentProps<typeof Link>["href"];
  viewAllLabel?: string;
}) {
  return (
    <Card variant="soft" className="p-4">
      <CardHeader label={label} viewAllHref={viewAllHref} viewAllLabel={viewAllLabel} />
      {projects.length === 0 ? (
        <EmptyState icon={Briefcase} message={emptyMessage} />
      ) : (
        <div className="space-y-4">
          {projects.map((project) => (
            <Link
              key={project.id}
              href={{ pathname: "/compte/projets/[id]", params: { id: String(project.id) } }}
              className="block"
            >
              <div className="mb-1.5 flex items-center justify-between gap-2 text-sm">
                <span className="min-w-0 truncate font-semibold" title={project.title}>
                  {project.title}
                </span>
                <span className="shrink-0 text-xs font-semibold text-(--color-muted)">
                  {progressLabel} {project.progress}%
                </span>
              </div>
              <div className="h-1.5 overflow-hidden rounded-full bg-(--color-surface-muted)">
                <div className="h-full rounded-full bg-brand-primary" style={{ width: `${project.progress}%` }} />
              </div>
            </Link>
          ))}
        </div>
      )}
    </Card>
  );
}

/** Table dense des dernières factures — quatrième carte du dashboard (mockup validé), à la place de la liste de blocs pleine largeur précédente. */
function RecentInvoicesTable({
  invoices,
  label,
  emptyMessage,
  columns,
  viewAllHref,
  viewAllLabel,
}: {
  invoices: SessionInvoice[];
  label: string;
  emptyMessage: string;
  columns: { invoice: string; project: string; amount: string; status: string };
  viewAllHref?: ComponentProps<typeof Link>["href"];
  viewAllLabel?: string;
}) {
  return (
    <Card variant="soft" className="p-4">
      <CardHeader label={label} viewAllHref={viewAllHref} viewAllLabel={viewAllLabel} />
      {invoices.length === 0 ? (
        <EmptyState icon={Receipt} message={emptyMessage} />
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead>
              <tr className="text-[10.5px] font-bold uppercase tracking-wide text-(--color-muted)">
                <th className="pb-2 font-bold">{columns.invoice}</th>
                <th className="pb-2 font-bold">{columns.project}</th>
                <th className="pb-2 text-right font-bold">{columns.amount}</th>
                <th className="pb-2 pl-3 font-bold">{columns.status}</th>
              </tr>
            </thead>
            <tbody>
              {invoices.map((invoice) => (
                <tr key={invoice.id} className="border-t border-(--border-neutral)">
                  <td className="py-2 font-mono text-xs">{invoice.label}</td>
                  <td className="max-w-[9rem] truncate py-2">{invoice.projectTitle}</td>
                  <td className="py-2 text-right font-mono text-xs font-semibold">{invoice.formattedConvertedAmount}</td>
                  <td className="py-2 pl-3">
                    <Badge variant={invoiceStatusVariant(invoice.status)}>{invoice.statusLabel}</Badge>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </Card>
  );
}

export default async function ComptePage({
  params,
  searchParams,
}: {
  params: Promise<{ locale: string }>;
  searchParams: Promise<{ welcome?: string }>;
}) {
  const { locale } = await params;
  const { welcome } = await searchParams;
  const user = await getCurrentUser();
  const t = await getTranslations({ locale, namespace: "auth.account" });

  // La garde du layout redirige déjà si null ; ce fallback satisfait le typage.
  if (!user) return null;

  const { attributions, isCollaborator } = user;
  const roleLabel = isCollaborator ? t("roleFreelance") : t("roleClient");

  const collaboratingProjects = [
    ...attributions.collaboratingProjects,
    ...attributions.ownedProjects,
  ];

  const allProjects = [...collaboratingProjects, ...attributions.clientProjects];
  const deadlines = upcomingDeadlines(allProjects, UPCOMING_COUNT);
  const activity = await getMyActivity();

  const activeProjects = activeProjectsOf(allProjects);
  const avgProgress =
    activeProjects.length > 0
      ? Math.round(activeProjects.reduce((sum, p) => sum + p.progress, 0) / activeProjects.length)
      : null;

  const pendingQuotesCount = attributions.quoteRequests.filter((q) => q.status === "pending").length;
  const unpaidInvoicesCount = attributions.invoices.filter((inv) => inv.status === "pending").length;
  const recentInvoices = [...attributions.invoices]
    .sort((a, b) => new Date(b.issuedAt).getTime() - new Date(a.issuedAt).getTime())
    .slice(0, PREVIEW_COUNT);

  const revenuePoints = monthlyInvoiceTotals(attributions.invoices, 6, locale);
  const statusSlices = projectsByStatus(allProjects);

  return (
    <div className="space-y-8">
      {welcome === "1" && (
        <WelcomeBanner message={t("welcome", { name: user.fullName ?? user.email })} />
      )}

      {/* ---- En-tête plat : titre + sous-titre nus, comme sur toutes les pages de l'espace compte (cf. PageHeader). Les badges de statut (rôle, vérification) restent, en discret, à côté du titre. */}
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div className="min-w-0">
          <div className="flex flex-wrap items-center gap-1.5">
            <Badge variant="neutral">{roleLabel}</Badge>
            <Badge variant="neutral">{user.isVerified ? t("verifiedBadge") : t("unverifiedBadge")}</Badge>
          </div>
          <h1 className="mt-2 text-[21px] font-extrabold tracking-tight" style={{ fontFamily: "var(--font-heading)" }}>
            {t("dashboardTitle")}
          </h1>
          <p className="mt-0.5 text-[12.5px] font-semibold text-(--color-muted)">{t("heroSubtitle")}</p>
        </div>
      </div>

      {!user.isVerified && (
        <Card className="border-l-4 border-warning p-4 text-sm">
          {t("verifyWarning")}
        </Card>
      )}

      {/* ---- Résumé ---- */}
      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        {avgProgress !== null && (
          <ActiveProjectsTile
            count={activeProjects.length}
            avgProgress={avgProgress}
            label={t("stats.activeProjects")}
            caption={t("hero.progressAvgCaption", { percent: avgProgress })}
          />
        )}
        <StatCard
          icon={FileText}
          label={t("stats.pendingQuotes")}
          value={pendingQuotesCount}
          href="/compte/devis"
        />
        {isCollaborator && (
          <StatCard
            icon={Briefcase}
            label={t("stats.assignedProjects")}
            value={collaboratingProjects.length}
            href="/compte/gestion-projet"
          />
        )}
        {attributions.invoices.length > 0 && (
          <StatCard
            icon={Receipt}
            label={t("stats.unpaidInvoices")}
            value={unpaidInvoicesCount}
            href="/compte/factures"
            tone={unpaidInvoicesCount > 0 ? "warning" : "default"}
          />
        )}
      </div>

      {/* ---- Aperçu : facturé, statuts, échéances — trois cartes côte à côte, comme la maquette validée (plus de section pleine largeur par carte). ---- */}
      <p className="text-[11.5px] font-bold uppercase tracking-wide text-(--color-muted)">{t("sections.overview")}</p>
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <Card variant="soft" className="p-4">
          <RevenueChart points={revenuePoints} label={t("sections.revenueChart")} />
        </Card>
        <Card variant="soft" className="p-4">
          <StatusDonut slices={statusSlices} label={t("sections.projectsByStatus")} />
        </Card>
        <UpcomingDeadlinesCard
          projects={deadlines}
          locale={locale}
          label={t("sections.upcomingDeadlines")}
          emptyMessage={t("sections.emptyDeadlines")}
          overdueLabel={t("sections.overdue")}
        />
      </div>

      {/* ---- Activité : historique + messages, deux cartes côte à côte. ---- */}
      <p className="text-[11.5px] font-bold uppercase tracking-wide text-(--color-muted)">{t("sections.activityGroup")}</p>
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <ActivityFeed
          entries={activity.history.slice(0, FEED_COUNT)}
          locale={locale}
          label={t("sections.activity")}
          emptyMessage={t("sections.emptyActivity")}
        />
        <MessagesFeed
          messages={activity.messages.slice(0, FEED_COUNT)}
          locale={locale}
          label={t("sections.messages")}
          emptyMessage={t("sections.emptyMessages")}
          youLabel={t("projectDetail.you")}
        />
      </div>

      {/* ---- Projets & facturation : avancement des projets en cours + dernières factures en table dense. ---- */}
      <p className="text-[11.5px] font-bold uppercase tracking-wide text-(--color-muted)">{t("sections.projectsAndBilling")}</p>
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <InProgressProjects
          projects={activeProjects.slice(0, PREVIEW_COUNT)}
          label={t("sections.inProgressProjects")}
          emptyMessage={t("sections.emptyProjects")}
          progressLabel={t("project.progress")}
          viewAllHref={activeProjects.length > PREVIEW_COUNT ? (isCollaborator ? "/compte/gestion-projet" : "/compte/projets") : undefined}
          viewAllLabel={t("sections.viewAll")}
        />
        <RecentInvoicesTable
          invoices={recentInvoices}
          label={t("sections.recentInvoices")}
          emptyMessage={t("sections.emptyInvoices")}
          columns={{
            invoice: t("invoiceTableColumns.invoice"),
            project: t("invoiceTableColumns.project"),
            amount: t("invoiceTableColumns.amount"),
            status: t("invoiceTableColumns.status"),
          }}
          viewAllHref={attributions.invoices.length > PREVIEW_COUNT ? "/compte/factures" : undefined}
          viewAllLabel={t("sections.viewAll")}
        />
      </div>
    </div>
  );
}
