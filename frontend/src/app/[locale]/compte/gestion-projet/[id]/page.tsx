import { notFound } from "next/navigation";
import { getTranslations } from "next-intl/server";
import { ArrowLeft, UserRoundCheck } from "lucide-react";
import { Alert, Badge, ButtonLink, Card, Breadcrumb, SectionHeading } from "@/components/ui";
import { ProjectDiscussion } from "@/components/sections/ProjectDiscussion";
import { ProjectTaskBoard } from "@/components/sections/ProjectTaskBoard";
import { ProjectTimeTracking } from "@/components/sections/ProjectTimeTracking";
import {
  getCurrentUser,
  getMyProject,
  getMyProjectTeam,
  getMyProjectTasks,
  getMyProjectTimeTracking,
  getProjectComments,
} from "@/lib/auth/session";
import { getAvatarUrl } from "@/lib/media";
import { projectStatusVariant } from "@/lib/status";
import type { TaskStatus } from "@/lib/types";

/** Nombre de jours entre aujourd'hui et la deadline (négatif si dépassée). */
function daysUntil(deadline: string): number {
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const target = new Date(deadline);
  target.setHours(0, 0, 0, 0);
  return Math.round((target.getTime() - today.getTime()) / 86_400_000);
}

export default async function GestionProjetDetailPage({
  params,
}: {
  params: Promise<{ locale: string; id: string }>;
}) {
  const { locale, id } = await params;
  const projectId = Number(id);
  if (!Number.isInteger(projectId)) {
    notFound();
  }

  // Garde d'appartenance à l'équipe — exclut le client, contrairement à
  // getMyProject() : cet espace est réservé aux collaborateurs/owner.
  const team = await getMyProjectTeam(projectId);
  if (!team) {
    notFound();
  }

  const project = await getMyProject(projectId);
  if (!project) {
    notFound();
  }

  const [tasks, timeTracking, comments, user, t, td, tStatus, tw] = await Promise.all([
    getMyProjectTasks(projectId),
    getMyProjectTimeTracking(projectId),
    getProjectComments(projectId),
    getCurrentUser(),
    getTranslations({ locale, namespace: "auth.account" }),
    getTranslations({ locale, namespace: "projects.detail" }),
    getTranslations({ locale, namespace: "projects.status" }),
    getTranslations({ locale, namespace: "auth.workspace" }),
  ]);

  const readOnly = user?.isCollaborator ? user.profileCompletion < 100 : false;
  const remainingDays = project.deadline && project.status !== "termine" ? daysUntil(project.deadline) : null;

  return (
    <div className="max-w-[760px] space-y-6">
      <Breadcrumb
        items={[
          { label: t("nav.projectManagement"), href: "/compte/gestion-projet" },
          { label: project.title },
        ]}
      />

      <ButtonLink href="/compte/gestion-projet" variant="secondary" className="w-fit gap-1.5">
        <ArrowLeft size={15} aria-hidden="true" />
        {tw("backLink")}
      </ButtonLink>

      {readOnly && (
        <Alert
          variant="warning"
          icon={UserRoundCheck}
          title={tw("readOnlyBanner.title")}
          action={<ButtonLink href="/compte/profil">{tw("readOnlyBanner.cta")}</ButtonLink>}
        >
          {tw("readOnlyBanner.body")}
        </Alert>
      )}

      <div>
        <div className="mb-3 flex flex-wrap items-center gap-2">
          <Badge variant={projectStatusVariant(project.status)}>{tStatus(project.status)}</Badge>
          {project.priorityLabel && <Badge variant="neutral">{project.priorityLabel}</Badge>}
        </div>
        <h1 className="mb-2 text-[clamp(1.6rem,3vw,2.2rem)]">{project.title}</h1>
      </div>

      <Card variant="soft" className="p-5">
        <div className="mb-1.5 flex items-center justify-between text-xs text-(--color-muted)">
          <span>{t("project.progress")}</span>
          <span className="font-semibold text-brand-dark">{project.progress}%</span>
        </div>
        <div className="mb-4 h-1.5 w-full overflow-hidden rounded-full bg-brand-light">
          <div
            className="h-full rounded-full bg-gradient-to-r from-brand-primary to-brand-accent transition-[width] duration-300"
            style={{ width: `${project.progress}%` }}
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

      <div>
        <SectionHeading title={tw("team.title")} />
        <Card variant="soft" className="divide-y divide-(--border-neutral) overflow-hidden p-0">
          <div className="flex items-center gap-3.5 p-4">
            {/* eslint-disable-next-line @next/next/no-img-element -- avatar externe ou média backend, cf. ProfilPage */}
            <img
              src={getAvatarUrl(team.owner)}
              alt=""
              className="h-11 w-11 shrink-0 rounded-full border border-(--border-neutral) object-cover"
            />
            <div className="min-w-0 flex-1">
              <p className="truncate text-sm font-semibold">{team.owner.fullName ?? team.owner.email}</p>
              <Badge variant="accent" className="mt-1">
                {tw("team.ownerLabel")}
              </Badge>
            </div>
          </div>
          {team.collaborators.map((member) => (
            <div key={member.id} className="flex items-center gap-3.5 p-4">
              {/* eslint-disable-next-line @next/next/no-img-element -- avatar externe ou média backend */}
              <img
                src={getAvatarUrl(member)}
                alt=""
                className="h-11 w-11 shrink-0 rounded-full border border-(--border-neutral) object-cover"
              />
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-semibold">{member.fullName ?? member.email}</p>
                <div className="mt-1 flex flex-wrap items-center gap-1.5">
                  {member.specialties && member.specialties.length > 0 ? (
                    member.specialties.map((specialty) => (
                      <Badge key={specialty} variant="neutral">
                        {specialty}
                      </Badge>
                    ))
                  ) : (
                    <Badge variant="neutral">{tw("team.collaboratorLabel")}</Badge>
                  )}
                  {member.availability && <span className="text-xs text-(--color-muted)">{member.availability}</span>}
                </div>
              </div>
            </div>
          ))}
        </Card>
      </div>

      <div>
        <SectionHeading title={tw("tasks.title")} />
        <ProjectTaskBoard
          projectId={projectId}
          initialTasks={tasks}
          readOnly={readOnly}
          labels={{
            empty: tw("tasks.empty"),
            notAssigned: tw("tasks.notAssigned"),
            updateError: tw("tasks.updateError"),
            dueLabel: tw("tasks.dueLabel"),
            overdueLabel: tw("tasks.overdueLabel"),
            statusLabels: {
              todo: tw("tasks.status.todo"),
              in_progress: tw("tasks.status.in_progress"),
              done: tw("tasks.status.done"),
              blocked: tw("tasks.status.blocked"),
            } satisfies Record<TaskStatus, string>,
          }}
        />
      </div>

      <div>
        <SectionHeading title={tw("time.title")} />
        <ProjectTimeTracking
          projectId={projectId}
          initialData={timeTracking}
          tasks={tasks}
          readOnly={readOnly}
          labels={{
            totalLabel: tw("time.totalLabel"),
            mineLabel: tw("time.mineLabel"),
            historyEmpty: tw("time.historyEmpty"),
            formTitle: tw("time.formTitle"),
            hoursLabel: tw("time.hoursLabel"),
            minutesLabel: tw("time.minutesLabel"),
            descriptionLabel: tw("time.descriptionLabel"),
            dateLabel: tw("time.dateLabel"),
            taskLabel: tw("time.taskLabel"),
            noTask: tw("time.noTask"),
            submit: tw("time.submit"),
            submitting: tw("time.submitting"),
            success: tw("time.success"),
          }}
        />
      </div>

      <ProjectDiscussion
        projectId={project.id}
        initialComments={comments}
        locale={locale}
        readOnly={readOnly}
        labels={{
          title: t("projectDetail.discussionLabel"),
          empty: t("projectDetail.emptyDiscussion"),
          placeholder: t("projectDetail.messagePlaceholder"),
          send: t("projectDetail.send"),
          sending: t("projectDetail.sending"),
          you: t("projectDetail.you"),
          close: t("projectDetail.closeDiscussion"),
          open: t("projectDetail.openDiscussion"),
          readOnly: tw("readOnlyBanner.discussionHint"),
        }}
      />
    </div>
  );
}
