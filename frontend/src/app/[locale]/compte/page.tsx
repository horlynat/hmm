import { getTranslations } from "next-intl/server";
import { FolderKanban, FileText, Receipt, Briefcase, Handshake, History, MessageSquare } from "lucide-react";
import { Badge, ButtonLink, Card, EmptyState, SectionHeading, StatCard } from "@/components/ui";
import { ProjectList, QuoteList } from "@/components/sections/AccountLists";
import { WelcomeBanner } from "@/components/sections/WelcomeBanner";
import { getCurrentUser, getMyActivity } from "@/lib/auth/session";
import { Link } from "@/i18n/navigation";
import { projectStatusVariant, invoiceStatusVariant } from "@/lib/status";
import type { SessionProject, SessionActivityEntry, SessionComment } from "@/lib/types";

const PREVIEW_COUNT = 4;
const UPCOMING_COUNT = 5;
const FEED_COUNT = 5;

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
function ProgressRing({ value, label, caption }: { value: number; label: string; caption: string }) {
  const size = 116;
  const stroke = 10;
  const radius = (size - stroke) / 2;
  const circumference = 2 * Math.PI * radius;
  const offset = circumference * (1 - value / 100);

  return (
    <div className="flex shrink-0 flex-col items-center gap-2 text-center">
      <div className="relative" style={{ width: size, height: size }}>
        <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`} className="-rotate-90">
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
        <div className="absolute inset-0 flex items-center justify-center">
          <span className="text-2xl font-bold" style={{ fontFamily: "var(--font-heading)" }}>
            {value}%
          </span>
        </div>
      </div>
      <div>
        <p className="text-xs font-semibold uppercase tracking-wider text-(--color-muted)">{label}</p>
        <p className="text-xs text-(--color-muted)">{caption}</p>
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

function ActivityFeed({
  entries,
  locale,
  emptyMessage,
}: {
  entries: SessionActivityEntry[];
  locale: string;
  emptyMessage: string;
}) {
  if (entries.length === 0) {
    return <EmptyState icon={History} message={emptyMessage} />;
  }

  return (
    <Card variant="soft" className="p-4">
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
    </Card>
  );
}

function MessagesFeed({
  messages,
  locale,
  emptyMessage,
  youLabel,
}: {
  messages: SessionComment[];
  locale: string;
  emptyMessage: string;
  youLabel: string;
}) {
  if (messages.length === 0) {
    return <EmptyState icon={MessageSquare} message={emptyMessage} />;
  }

  return (
    <Card variant="soft" className="p-4">
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

  const projectLabels = {
    progress: t("project.progress"),
    deadline: t("project.deadline"),
    noDeadline: t("project.noDeadline"),
  };

  const quoteStatusLabels = {
    pending: t("quoteStatus.pending"),
    accepted: t("quoteStatus.accepted"),
    suspended: t("quoteStatus.suspended"),
    rejected: t("quoteStatus.rejected"),
  };

  const collaboratingProjects = [
    ...attributions.collaboratingProjects,
    ...attributions.ownedProjects,
  ];
  const hasCollaborating = collaboratingProjects.length > 0;
  const hasClientProjects = attributions.clientProjects.length > 0;
  const hasQuotes = attributions.quoteRequests.length > 0;

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

  return (
    <div className="space-y-8">
      {welcome === "1" && (
        <WelcomeBanner message={t("welcome", { name: user.fullName ?? user.email })} />
      )}

      {/* ---- Bandeau d'ouverture : identité + vue d'ensemble, seul élément dominant de la page — le reste (stats, sections) reste volontairement en second plan. */}
      <div className="relative overflow-hidden rounded-[var(--radius-lg)] border border-(--border-neutral) bg-gradient-to-br from-brand-primary/[0.07] via-transparent to-transparent p-6 sm:p-8">
        <div className="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
          <div className="min-w-0">
            <div className="flex flex-wrap items-center gap-2">
              <Badge variant="neutral">{roleLabel}</Badge>
              <Badge variant="neutral">{user.isVerified ? t("verifiedBadge") : t("unverifiedBadge")}</Badge>
            </div>
            <h1
              className="mt-3 text-[clamp(1.6rem,3vw,2.15rem)] font-bold"
              style={{ fontFamily: "var(--font-heading)" }}
            >
              {t("dashboardTitle")}
            </h1>
            <p className="mt-2 max-w-md text-sm text-(--color-muted)">{t("heroSubtitle")}</p>
          </div>

          {avgProgress !== null && (
            <ProgressRing
              value={avgProgress}
              label={t("hero.progressLabel")}
              caption={t("hero.progressCaption", { count: activeProjects.length })}
            />
          )}
        </div>
      </div>

      {!user.isVerified && (
        <Card className="border-l-4 border-warning p-4 text-sm">
          {t("verifyWarning")}
        </Card>
      )}

      {/* ---- Résumé ---- */}
      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <StatCard icon={FolderKanban} label={t("stats.activeProjects")} value={activeProjects.length} />
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

      {deadlines.length > 0 && (
        <section aria-labelledby="section-deadlines">
          <SectionHeading id="section-deadlines" title={t("sections.upcomingDeadlines")} />
          <Card variant="soft" className="divide-y divide-(--border-neutral) overflow-hidden p-0">
            {deadlines.map((project) => {
              const overdue = new Date(project.deadline!) < new Date();
              return (
                <Link
                  key={project.id}
                  href={{ pathname: "/compte/projets/[id]", params: { id: String(project.id) } }}
                  className="flex flex-col gap-2 p-4 transition-colors hover:bg-(--color-surface-muted) sm:flex-row sm:items-center sm:justify-between"
                >
                  <div className="flex min-w-0 items-center gap-2">
                    <Badge variant={projectStatusVariant(project.status)}>{project.statusLabel}</Badge>
                    <span className="min-w-0 font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
                      {project.title}
                    </span>
                  </div>
                  <span className={overdue ? "shrink-0 text-sm font-semibold text-danger" : "shrink-0 text-sm opacity-70"}>
                    {overdue && `${t("sections.overdue")} — `}
                    {new Date(project.deadline!).toLocaleDateString(locale)}
                  </span>
                </Link>
              );
            })}
          </Card>
        </section>
      )}

      {/* ---- Activité & messages ---- */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <section aria-labelledby="section-activity">
          <SectionHeading id="section-activity" title={t("sections.activity")} />
          <ActivityFeed
            entries={activity.history.slice(0, FEED_COUNT)}
            locale={locale}
            emptyMessage={t("sections.emptyActivity")}
          />
        </section>

        <section aria-labelledby="section-messages">
          <SectionHeading id="section-messages" title={t("sections.messages")} />
          <MessagesFeed
            messages={activity.messages.slice(0, FEED_COUNT)}
            locale={locale}
            emptyMessage={t("sections.emptyMessages")}
            youLabel={t("projectDetail.you")}
          />
        </section>
      </div>

      {isCollaborator && (
        <section aria-labelledby="section-collaborating">
          <SectionHeading
            id="section-collaborating"
            title={t("sections.collaboratingProjects")}
            viewAllHref={collaboratingProjects.length > PREVIEW_COUNT ? "/compte/gestion-projet" : undefined}
            viewAllLabel={t("sections.viewAll")}
          />
          {hasCollaborating ? (
            <ProjectList projects={collaboratingProjects.slice(0, PREVIEW_COUNT)} labels={projectLabels} />
          ) : (
            <EmptyState icon={Handshake} message={t("sections.emptyProjects")} />
          )}
        </section>
      )}

      <section aria-labelledby="section-client-projects">
        <SectionHeading
          id="section-client-projects"
          title={t("sections.clientProjects")}
          viewAllHref={attributions.clientProjects.length > PREVIEW_COUNT ? "/compte/projets" : undefined}
          viewAllLabel={t("sections.viewAll")}
        />
        {hasClientProjects ? (
          <ProjectList projects={attributions.clientProjects.slice(0, PREVIEW_COUNT)} labels={projectLabels} />
        ) : (
          <EmptyState icon={FolderKanban} message={t("sections.emptyProjects")} />
        )}
      </section>

      <section aria-labelledby="section-quotes">
        <SectionHeading
          id="section-quotes"
          title={t("sections.quotes")}
          viewAllHref={attributions.quoteRequests.length > PREVIEW_COUNT ? "/compte/devis" : undefined}
          viewAllLabel={t("sections.viewAll")}
        />
        {hasQuotes ? (
          <QuoteList
            quotes={attributions.quoteRequests.slice(0, PREVIEW_COUNT)}
            statusLabel={t("sections.quoteBudget")}
            statusLabels={quoteStatusLabels}
            convertedLabel={t("sections.quoteConverted")}
          />
        ) : (
          <EmptyState
            icon={FileText}
            message={t("sections.emptyQuotes")}
            action={
              !isCollaborator && (
                <ButtonLink href="/compte/devis/nouveau" variant="secondary" className="text-xs">
                  {t("nav.newQuoteCta")}
                </ButtonLink>
              )
            }
          />
        )}
      </section>

      {recentInvoices.length > 0 && (
        <section aria-labelledby="section-invoices">
          <SectionHeading
            id="section-invoices"
            title={t("sections.recentInvoices")}
            viewAllHref={attributions.invoices.length > PREVIEW_COUNT ? "/compte/factures" : undefined}
            viewAllLabel={t("sections.viewAll")}
          />
          <Card variant="soft" className="divide-y divide-(--border-neutral) overflow-hidden p-0">
            {recentInvoices.map((invoice) => (
              <Link
                key={invoice.id}
                href="/compte/factures"
                className="flex flex-col gap-3 p-4 transition-colors hover:bg-(--color-surface-muted) sm:flex-row sm:items-center sm:justify-between"
              >
                <div className="flex min-w-0 items-center gap-3">
                  <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-(--color-surface-muted) text-brand-primary">
                    <Receipt size={14} aria-hidden="true" />
                  </span>
                  <div className="min-w-0">
                    <p className="text-sm font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
                      {invoice.label}
                    </p>
                    <p className="mt-0.5 truncate text-xs text-(--color-muted)">{invoice.projectTitle}</p>
                  </div>
                </div>
                <div className="flex shrink-0 items-center justify-between gap-2 sm:flex-col sm:items-end">
                  <span className="text-sm font-semibold">{invoice.formattedConvertedAmount}</span>
                  <Badge variant={invoiceStatusVariant(invoice.status)}>{invoice.statusLabel}</Badge>
                </div>
              </Link>
            ))}
          </Card>
        </section>
      )}
    </div>
  );
}
