import { getTranslations } from "next-intl/server";
import { Badge, Card } from "@/components/ui";
import { getCurrentUser } from "@/lib/auth/session";
import type { SessionProject, SessionQuote } from "@/lib/types";

function ProjectList({
  projects,
  labels,
}: {
  projects: SessionProject[];
  labels: { progress: string; deadline: string; noDeadline: string };
}) {
  return (
    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
      {projects.map((project) => (
        <Card key={project.id} variant="soft" className="p-4">
          <div className="mb-2 flex items-start justify-between gap-2">
            <span className="font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
              {project.title}
            </span>
            <Badge>{project.statusLabel}</Badge>
          </div>
          <div className="mb-1 flex items-center justify-between text-xs opacity-60">
            <span>{labels.progress}</span>
            <span>{project.progress}%</span>
          </div>
          <div className="h-1.5 w-full rounded-full bg-brand-light">
            <div
              className="h-1.5 rounded-full bg-brand-primary"
              style={{ width: `${project.progress}%` }}
            />
          </div>
          <p className="mt-2 text-xs opacity-60">
            {labels.deadline}:{" "}
            {project.deadline
              ? new Date(project.deadline).toLocaleDateString()
              : labels.noDeadline}
          </p>
        </Card>
      ))}
    </div>
  );
}

function QuoteList({
  quotes,
  statusLabel,
}: {
  quotes: SessionQuote[];
  statusLabel: string;
}) {
  return (
    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
      {quotes.map((quote) => (
        <Card key={quote.id} variant="soft" className="p-4">
          <div className="mb-1 flex items-start justify-between gap-2">
            <span className="font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
              {quote.category}
            </span>
            <Badge>{quote.status}</Badge>
          </div>
          {quote.budget && (
            <p className="text-sm opacity-70">
              {statusLabel}: {quote.budget} {quote.currency ?? ""}
            </p>
          )}
        </Card>
      ))}
    </div>
  );
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

  const collaboratingProjects = [
    ...attributions.collaboratingProjects,
    ...attributions.ownedProjects,
  ];
  const hasCollaborating = collaboratingProjects.length > 0;
  const hasClientProjects = attributions.clientProjects.length > 0;
  const hasQuotes = attributions.quoteRequests.length > 0;

  return (
    <div className="space-y-8">
      <div>
        <Badge variant="accent" className="mb-3">
          {roleLabel}
        </Badge>
        <h1 className="text-[clamp(1.6rem,3vw,2.2rem)]">
          {t("welcome", { name: user.fullName ?? user.email })}
        </h1>
      </div>

      {!user.isVerified && (
        <Card className="border-l-4 border-warning p-4 text-sm">
          {t("verifyWarning")}
        </Card>
      )}

      {isCollaborator && (
        <section>
          <h2 className="mb-3 text-lg font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
            {t("sections.collaboratingProjects")}
          </h2>
          {hasCollaborating ? (
            <ProjectList projects={collaboratingProjects} labels={projectLabels} />
          ) : (
            <p className="text-sm opacity-60">{t("sections.emptyProjects")}</p>
          )}
        </section>
      )}

      <section>
        <h2 className="mb-3 text-lg font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
          {t("sections.clientProjects")}
        </h2>
        {hasClientProjects ? (
          <ProjectList projects={attributions.clientProjects} labels={projectLabels} />
        ) : (
          <p className="text-sm opacity-60">{t("sections.emptyProjects")}</p>
        )}
      </section>

      <section>
        <h2 className="mb-3 text-lg font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
          {t("sections.quotes")}
        </h2>
        {hasQuotes ? (
          <QuoteList quotes={attributions.quoteRequests} statusLabel={t("sections.quoteBudget")} />
        ) : (
          <p className="text-sm opacity-60">{t("sections.emptyQuotes")}</p>
        )}
      </section>
    </div>
  );
}
