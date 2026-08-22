import { getTranslations } from "next-intl/server";
import { FolderKanban, Handshake, Activity, CalendarClock, CheckCircle2 } from "lucide-react";
import { EmptyState, SectionHeading, StatCard } from "@/components/ui";
import { ProjectList } from "@/components/sections/AccountLists";
import type { SessionUser } from "@/lib/types";

/**
 * Corps de "Mes projets" — extrait de /compte/projets pour être partagé avec
 * l'onglet "Mes projets" du hub /compte/gestion-projet (cf. la maquette de
 * refonte validée : plus de lien dédié dans l'aside pour un collaborateur,
 * cet onglet en tient lieu). `hrefPattern` ne s'applique qu'à la section
 * "Projets qui me sont confiés" : la section client pointe toujours vers la
 * fiche vitrine /compte/projets/[id], jamais vers l'espace de travail
 * freelance auquel un client n'a pas accès.
 */
export async function MyProjectsPanel({
  user,
  locale,
  collaboratingHrefPattern = "/compte/projets/[id]",
}: {
  user: SessionUser;
  locale: string;
  collaboratingHrefPattern?: "/compte/projets/[id]" | "/compte/gestion-projet/[id]";
}) {
  const t = await getTranslations({ locale, namespace: "auth.account" });
  const { attributions, isCollaborator } = user;
  const collaboratingProjects = [...attributions.collaboratingProjects, ...attributions.ownedProjects];
  const allProjects = [...attributions.clientProjects, ...collaboratingProjects];

  const projectLabels = {
    progress: t("project.progress"),
    deadline: t("project.deadline"),
    noDeadline: t("project.noDeadline"),
  };

  return (
    <div className="space-y-8">
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
          <EmptyState icon={FolderKanban} message={t("sections.emptyProjects")} />
        )}
      </section>

      {isCollaborator && (
        <section aria-labelledby="section-collaborating">
          <SectionHeading id="section-collaborating" title={t("sections.collaboratingProjects")} />
          {collaboratingProjects.length > 0 ? (
            <ProjectList
              projects={collaboratingProjects}
              labels={projectLabels}
              hrefPattern={collaboratingHrefPattern}
            />
          ) : (
            <EmptyState icon={Handshake} message={t("sections.emptyProjects")} />
          )}
        </section>
      )}
    </div>
  );
}
