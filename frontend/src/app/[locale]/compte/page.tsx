import { getTranslations } from "next-intl/server";
import { Badge, Card, ButtonLink, EmptyState } from "@/components/ui";
import { ProjectList, QuoteList } from "@/components/sections/AccountLists";
import { getCurrentUser } from "@/lib/auth/session";
import { getAvatarUrl } from "@/lib/media";
import { Link } from "@/i18n/navigation";
import { projectStatusVariant } from "@/lib/status";
import type { SessionProject } from "@/lib/types";

const PREVIEW_COUNT = 4;
const UPCOMING_COUNT = 5;

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

export default async function ComptePage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
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

  const deadlines = upcomingDeadlines(
    [...collaboratingProjects, ...attributions.clientProjects],
    UPCOMING_COUNT,
  );

  return (
    <div className="space-y-8">
      <div className="flex items-center gap-4">
        {/* eslint-disable-next-line @next/next/no-img-element -- avatar externe (ui-avatars.com) ou média backend, hors domaines optimisables par next/image sans config supplémentaire */}
        <img
          src={getAvatarUrl(user)}
          alt=""
          className="h-16 w-16 shrink-0 rounded-full border border-[var(--border-soft)] object-cover"
        />
        <div>
          <div className="mb-2 flex flex-wrap items-center gap-2">
            <Badge variant="neutral">{roleLabel}</Badge>
            <Badge variant="neutral">
              {user.isVerified ? t("verifiedBadge") : t("unverifiedBadge")}
            </Badge>
          </div>
          <h1 className="text-[clamp(1.6rem,3vw,2.2rem)]">
            {t("welcome", { name: user.fullName ?? user.email })}
          </h1>
        </div>
      </div>

      {!user.isVerified && (
        <Card className="border-l-4 border-warning p-4 text-sm">
          {t("verifyWarning")}
        </Card>
      )}

      {deadlines.length > 0 && (
        <section aria-labelledby="section-deadlines">
          <h2 id="section-deadlines" className="mb-3 text-lg font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
            {t("sections.upcomingDeadlines")}
          </h2>
          <Card variant="soft" className="divide-y divide-(--border-neutral) overflow-hidden p-0">
            {deadlines.map((project) => {
              const overdue = new Date(project.deadline!) < new Date();
              return (
                <Link
                  key={project.id}
                  href={{ pathname: "/compte/projets/[id]", params: { id: String(project.id) } }}
                  className="flex items-center justify-between gap-3 p-4 transition-colors hover:bg-(--color-surface-muted)"
                >
                  <div className="flex min-w-0 items-center gap-2">
                    <Badge variant={projectStatusVariant(project.status)}>{project.statusLabel}</Badge>
                    <span className="truncate font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
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

      {isCollaborator && (
        <section aria-labelledby="section-collaborating">
          <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 id="section-collaborating" className="text-lg font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
              {t("sections.collaboratingProjects")}
            </h2>
            {collaboratingProjects.length > PREVIEW_COUNT && (
              <ButtonLink href="/compte/gestion-projet" variant="secondary" className="text-xs">
                {t("sections.viewAll")}
              </ButtonLink>
            )}
          </div>
          {hasCollaborating ? (
            <ProjectList projects={collaboratingProjects.slice(0, PREVIEW_COUNT)} labels={projectLabels} />
          ) : (
            <EmptyState icon="🤝" message={t("sections.emptyProjects")} />
          )}
        </section>
      )}

      <section aria-labelledby="section-client-projects">
        <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
          <h2 id="section-client-projects" className="text-lg font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
            {t("sections.clientProjects")}
          </h2>
          {attributions.clientProjects.length > PREVIEW_COUNT && (
            <ButtonLink href="/compte/projets" variant="secondary" className="text-xs">
              {t("sections.viewAll")}
            </ButtonLink>
          )}
        </div>
        {hasClientProjects ? (
          <ProjectList projects={attributions.clientProjects.slice(0, PREVIEW_COUNT)} labels={projectLabels} />
        ) : (
          <EmptyState icon="📁" message={t("sections.emptyProjects")} />
        )}
      </section>

      <section aria-labelledby="section-quotes">
        <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
          <h2 id="section-quotes" className="text-lg font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
            {t("sections.quotes")}
          </h2>
          {attributions.quoteRequests.length > PREVIEW_COUNT && (
            <ButtonLink href="/compte/devis" variant="secondary" className="text-xs">
              {t("sections.viewAll")}
            </ButtonLink>
          )}
        </div>
        {hasQuotes ? (
          <QuoteList
            quotes={attributions.quoteRequests.slice(0, PREVIEW_COUNT)}
            statusLabel={t("sections.quoteBudget")}
            statusLabels={quoteStatusLabels}
          />
        ) : (
          <EmptyState icon="📝" message={t("sections.emptyQuotes")} />
        )}
      </section>
    </div>
  );
}
