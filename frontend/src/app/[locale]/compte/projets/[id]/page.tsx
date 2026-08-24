import Image from "next/image";
import { notFound } from "next/navigation";
import { getTranslations } from "next-intl/server";
import { ArrowLeft, Receipt } from "lucide-react";
import { Badge, Card, ButtonLink, Breadcrumb, SectionHeading } from "@/components/ui";
import { ProjectDiscussion } from "@/components/sections/ProjectDiscussion";
import { getCurrentUser, getMyProject, getProjectComments } from "@/lib/auth/session";
import { getMediaUrl } from "@/lib/media";
import { projectStatusVariant, invoiceStatusVariant } from "@/lib/status";
import { sanitizeArticleHtml } from "@/lib/sanitize";

/** Nombre de jours entre aujourd'hui et la deadline (négatif si dépassée) — comparaison sur la seule date, pas l'heure. */
function daysUntil(deadline: string): number {
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const target = new Date(deadline);
  target.setHours(0, 0, 0, 0);
  return Math.round((target.getTime() - today.getTime()) / 86_400_000);
}

export default async function CompteProjectDetailPage({
  params,
}: {
  params: Promise<{ locale: string; id: string }>;
}) {
  const { locale, id } = await params;
  const projectId = Number(id);
  if (!Number.isInteger(projectId)) {
    notFound();
  }

  const project = await getMyProject(projectId);
  if (!project) {
    notFound();
  }

  const [t, td, tStatus, comments, user] = await Promise.all([
    getTranslations({ locale, namespace: "auth.account" }),
    getTranslations({ locale, namespace: "projects.detail" }),
    getTranslations({ locale, namespace: "projects.status" }),
    getProjectComments(projectId),
    getCurrentUser(),
  ]);

  const info = project.info;
  const projectInvoices = user?.attributions.invoices.filter((inv) => inv.projectId === project.id) ?? [];
  const remainingDays = project.deadline && project.status !== "termine" ? daysUntil(project.deadline) : null;

  return (
    <div className="max-w-[760px] space-y-6">
      <Breadcrumb
        items={[
          { label: t("nav.myProjects"), href: "/compte/projets" },
          { label: project.title },
        ]}
      />

      <ButtonLink href="/compte" variant="secondary" className="w-fit gap-1.5">
        <ArrowLeft size={15} aria-hidden="true" />
        {t("backLink")}
      </ButtonLink>

      <div>
        <div className="mb-3 flex flex-wrap items-center gap-2">
          <Badge variant={projectStatusVariant(project.status)}>{tStatus(project.status)}</Badge>
          {project.priorityLabel && <Badge variant="neutral">{project.priorityLabel}</Badge>}
          {project.billingTypeLabel && <Badge variant="neutral">{project.billingTypeLabel}</Badge>}
        </div>
        <h1 className="mb-2 text-[clamp(1.6rem,3vw,2.2rem)]">{project.title}</h1>
        {info?.role && (
          <p className="text-sm font-semibold text-brand-primary">
            {td("roleLabel")} — {info.role}
          </p>
        )}
      </div>

      <Card variant="soft" className="p-5">
        <div className="mb-1.5 flex items-center justify-between text-xs text-(--color-muted)">
          <span>{t("project.progress")}</span>
          <span className="font-semibold text-brand-dark">{project.progress}%</span>
        </div>
        <div className="mb-4 h-1.5 w-full overflow-hidden rounded-full bg-brand-light">
          <div
            className="h-full w-full origin-left rounded-full bg-gradient-to-r from-brand-primary to-brand-accent transition-transform duration-300"
            style={{ transform: `scaleX(${project.progress / 100})` }}
          />
        </div>
        <div className="flex flex-wrap items-center justify-between gap-2">
          <p className="text-sm text-(--color-muted)">
            {t("project.deadline")}:{" "}
            <span className="font-medium text-brand-dark">
              {project.deadline ? new Date(project.deadline).toLocaleDateString() : t("project.noDeadline")}
            </span>
          </p>
          {remainingDays !== null && (
            <Badge variant={remainingDays < 0 ? "danger" : remainingDays <= 7 ? "warning" : "neutral"}>
              {remainingDays < 0
                ? td("deadlineOverdueDays", { days: Math.abs(remainingDays) })
                : td("deadlineInDays", { days: remainingDays })}
            </Badge>
          )}
        </div>
      </Card>

      {projectInvoices.length > 0 && (
        <div>
          <SectionHeading
            title={td("invoicesLabel")}
            viewAllHref="/compte/factures"
            viewAllLabel={td("viewAllInvoices")}
          />
          <Card variant="soft" className="divide-y divide-(--border-neutral) overflow-hidden p-0">
            {projectInvoices.map((invoice) => (
              <div key={invoice.id} className="flex items-center gap-3 p-4">
                <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-(--color-surface-muted) text-brand-primary">
                  <Receipt size={15} aria-hidden="true" />
                </span>
                <div className="min-w-0 flex-1">
                  <p className="truncate text-sm font-semibold">{invoice.label}</p>
                  <p className="text-xs text-(--color-muted)">{invoice.number}</p>
                </div>
                <span className="shrink-0 font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
                  {invoice.formattedConvertedAmount}
                </span>
                <Badge variant={invoiceStatusVariant(invoice.status)}>{invoice.statusLabel}</Badge>
              </div>
            ))}
          </Card>
        </div>
      )}

      <div>
        <SectionHeading title={t("projectDetail.descriptionLabel")} />
        {/* Contenu HTML rédigé côté admin (éditeur riche), sanitisé côté
            serveur avant injection — même traitement que la page publique
            du projet, cf. (marketing)/realisations/[slug]/page.tsx. */}
        <div
          className="article-body text-sm opacity-80"
          dangerouslySetInnerHTML={{ __html: sanitizeArticleHtml(project.description) }}
        />
      </div>

      {(project.skills.length > 0 || project.tags.length > 0) && (
        <div className="flex flex-wrap gap-1.5">
          {project.skills.map((skill) => (
            <Badge key={skill.id} variant="accent">
              {skill.name}
            </Badge>
          ))}
          {project.tags.map((tag) => (
            <Badge key={tag.id} variant="outline">
              #{tag.name}
            </Badge>
          ))}
        </div>
      )}

      {info?.coverImage && (
        <Card variant="soft" className="overflow-hidden p-0">
          <div className="relative h-[220px] w-full bg-brand-light sm:h-[300px]">
            <Image
              src={getMediaUrl(info.coverImage.filePath)}
              alt={info.coverImage.altText ?? project.title}
              fill
              sizes="760px"
              className="object-cover"
            />
          </div>
        </Card>
      )}

      {info && info.objectives.length > 0 && (
        <div>
          <SectionHeading title={td("objectivesLabel")} />
          <ul className="flex flex-col gap-2 text-sm opacity-80">
            {info.objectives.map((objective) => (
              <li key={objective}>• {objective}</li>
            ))}
          </ul>
        </div>
      )}

      {info && info.techStack.length > 0 && (
        <div>
          <SectionHeading title={td("stackLabel")} />
          <div className="flex flex-col gap-3">
            {info.techStack.map((tech) => (
              <Card key={tech.name} variant="soft" className="p-3.5">
                <Badge variant="accent" className="mb-1.5 w-fit">
                  {tech.name}
                </Badge>
                {tech.rationale && <p className="text-sm opacity-70">{tech.rationale}</p>}
              </Card>
            ))}
          </div>
        </div>
      )}

      {info && info.challenges.length > 0 && (
        <div>
          <SectionHeading title={td("challengesLabel")} />
          <div className="flex flex-col gap-3">
            {info.challenges.map((challenge) => (
              <Card key={challenge.problem} variant="soft" className="grid gap-3 p-5 sm:grid-cols-2">
                <div>
                  <Badge variant="neutral" className="mb-2">
                    {td("challengeLabel")}
                  </Badge>
                  <p className="text-sm opacity-80">{challenge.problem}</p>
                </div>
                <div>
                  <Badge variant="neutral" className="mb-2">
                    {td("solutionLabel")}
                  </Badge>
                  <p className="text-sm opacity-80">{challenge.solution}</p>
                </div>
              </Card>
            ))}
          </div>
        </div>
      )}

      {info && info.results.length > 0 && (
        <div>
          <SectionHeading title={td("resultsLabel")} />
          <div className="grid gap-3 sm:grid-cols-2">
            {info.results.map((result) => (
              <Card key={result.label} variant="soft" className="p-4 text-center">
                <div
                  className="mb-1 text-[clamp(1.25rem,3vw,1.6rem)] font-semibold text-brand-primary"
                  style={{ fontFamily: "var(--font-heading)" }}
                >
                  {result.value}
                </div>
                <div className="text-sm opacity-70">{result.label}</div>
              </Card>
            ))}
          </div>
        </div>
      )}

      {project.media.length > 0 && (
        <div>
          <SectionHeading title={td("galleryLabel")} />
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
            {project.media.map((media) => (
              <div key={media.id} className="relative aspect-square overflow-hidden rounded-[var(--radius-md)] bg-bg-card">
                {media.mimeType?.includes("image") ? (
                  <Image
                    src={getMediaUrl(media.filePath)}
                    alt={media.altText ?? project.title}
                    fill
                    sizes="(min-width: 640px) 240px, 50vw"
                    className="object-cover"
                  />
                ) : null}
              </div>
            ))}
          </div>
        </div>
      )}

      {(info?.repoUrl || project.link) && (
        <div className="flex flex-wrap gap-3">
          {project.link && (
            <a href={project.link} target="_blank" rel="noopener noreferrer" className="btn-secondary">
              {project.link}
            </a>
          )}
          {info?.repoUrl && (
            <a
              href={info.repoUrl}
              target="_blank"
              rel="noopener"
              className="text-sm font-semibold text-brand-primary hover:underline"
            >
              {td("repoLink")} →
            </a>
          )}
        </div>
      )}

      <ProjectDiscussion
        projectId={project.id}
        initialComments={comments}
        locale={locale}
        labels={{
          title: t("projectDetail.discussionLabel"),
          empty: t("projectDetail.emptyDiscussion"),
          placeholder: t("projectDetail.messagePlaceholder"),
          send: t("projectDetail.send"),
          sending: t("projectDetail.sending"),
          you: t("projectDetail.you"),
          close: t("projectDetail.closeDiscussion"),
          open: t("projectDetail.openDiscussion"),
        }}
      />
    </div>
  );
}
