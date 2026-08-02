import { getTranslations } from "next-intl/server";
import { FolderKanban } from "lucide-react";
import { EmptyState, PageHeader } from "@/components/ui";
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

  const projectLabels = {
    progress: t("project.progress"),
    deadline: t("project.deadline"),
    noDeadline: t("project.noDeadline"),
  };

  return (
    <div className="space-y-8">
      <PageHeader icon={FolderKanban} title={t("nav.myProjects")} subtitle={t("myProjectsPage.subtitle")} />

      <section aria-labelledby="section-client-projects">
        <h2 id="section-client-projects" className="mb-3 text-lg font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
          {t("sections.clientProjects")}
        </h2>
        {attributions.clientProjects.length > 0 ? (
          <ProjectList projects={attributions.clientProjects} labels={projectLabels} />
        ) : (
          <EmptyState icon="📁" message={t("sections.emptyProjects")} />
        )}
      </section>

      {isCollaborator && (
        <section aria-labelledby="section-collaborating">
          <h2 id="section-collaborating" className="mb-3 text-lg font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
            {t("sections.collaboratingProjects")}
          </h2>
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
