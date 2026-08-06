import { getTranslations } from "next-intl/server";
import { FolderKanban, Activity, CalendarClock, CheckCircle2 } from "lucide-react";
import { EmptyState, PageHeader, SectionHeading, StatCard } from "@/components/ui";
import { ProjectList } from "@/components/sections/AccountLists";
import { getCurrentUser } from "@/lib/auth/session";

export default async function ComptProjetsPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const user = await getCurrentUser();
  const t = await getTranslations({ locale, namespace: "auth.account" });

  if (!user) return null;

  const { attributions, isCollaborator } = user;
  const collaboratingProjects = [
    ...attributions.collaboratingProjects,
    ...attributions.ownedProjects,
  ];
  const allProjects = [...attributions.clientProjects, ...collaboratingProjects];

  const projectLabels = {
    progress: t("project.progress"),
    deadline: t("project.deadline"),
    noDeadline: t("project.noDeadline"),
  };

  return (
    <div className="space-y-8">
      <PageHeader icon={FolderKanban} title={t("nav.myProjects")} subtitle={t("myProjectsPage.subtitle")} />

      {allProjects.length > 0 && (
        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
          <StatCard icon={FolderKanban} label={t("myProjectsPage.statTotal")} value={allProjects.length} />
          <StatCard
            icon={Activity}
            label={t("myProjectsPage.statActive")}
            value={allProjects.filter((p) => p.status === "en_cours").length}
            tone="success"
          />
          <StatCard
            icon={CalendarClock}
            label={t("myProjectsPage.statUpcoming")}
            value={allProjects.filter((p) => p.status === "a_venir").length}
          />
          <StatCard
            icon={CheckCircle2}
            label={t("myProjectsPage.statCompleted")}
            value={allProjects.filter((p) => p.status === "termine").length}
          />
        </div>
      )}

      <section aria-labelledby="section-client-projects">
        <SectionHeading id="section-client-projects" title={t("sections.clientProjects")} />
        {attributions.clientProjects.length > 0 ? (
          <ProjectList projects={attributions.clientProjects} labels={projectLabels} />
        ) : (
          <EmptyState icon="📁" message={t("sections.emptyProjects")} />
        )}
      </section>

      {isCollaborator && (
        <section aria-labelledby="section-collaborating">
          <SectionHeading id="section-collaborating" title={t("sections.collaboratingProjects")} />
          {collaboratingProjects.length > 0 ? (
            <ProjectList projects={collaboratingProjects} labels={projectLabels} />
          ) : (
            <EmptyState icon="🤝" message={t("sections.emptyProjects")} />
          )}
        </section>
      )}
    </div>
  );
}
