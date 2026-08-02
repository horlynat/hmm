import { getTranslations } from "next-intl/server";
import { EmptyState } from "@/components/ui";
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
      <div>
        <h1 className="mb-2 text-[clamp(1.6rem,3vw,2.2rem)]">{t("nav.myProjects")}</h1>
        <p className="opacity-70">{t("myProjectsPage.subtitle")}</p>
      </div>

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
