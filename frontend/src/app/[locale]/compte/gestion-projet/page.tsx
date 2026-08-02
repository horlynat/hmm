import { getTranslations } from "next-intl/server";
import { EmptyState } from "@/components/ui";
import { ProjectList } from "@/components/sections/AccountLists";
import { redirect } from "@/i18n/navigation";
import { getCurrentUser } from "@/lib/auth/session";

/**
 * Réservé aux freelances/collaborateurs (isCollaborator) : un client redirigé
 * ici n'a rien à y gérer — même règle de périmètre que ProjectVoter côté
 * back-office. Garde serveur, pas seulement un lien masqué dans le nav.
 */
export default async function GestionProjetPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const user = await getCurrentUser();
  if (!user) return null;

  if (!user.isCollaborator) {
    redirect({ href: "/compte", locale });
  }

  const t = await getTranslations({ locale, namespace: "auth.account" });
  const tg = await getTranslations({ locale, namespace: "auth.projectManagement" });

  const projects = [...user.attributions.collaboratingProjects, ...user.attributions.ownedProjects];
  const activeCount = projects.filter((p) => p.status === "en_cours").length;
  const upcomingCount = projects.filter((p) => p.status === "a_venir").length;

  const projectLabels = {
    progress: t("project.progress"),
    deadline: t("project.deadline"),
    noDeadline: t("project.noDeadline"),
  };

  return (
    <div className="space-y-8">
      <div>
        <h1 className="mb-2 text-[clamp(1.6rem,3vw,2.2rem)]">{tg("title")}</h1>
        <p className="opacity-70">{tg("subtitle")}</p>
      </div>

      <div className="grid grid-cols-1 divide-y divide-(--border-neutral) rounded-md border border-(--border-neutral) bg-bg-card sm:grid-cols-3 sm:divide-x sm:divide-y-0">
        <div className="p-4 text-center">
          <p className="text-2xl font-semibold text-brand-primary" style={{ fontFamily: "var(--font-heading)" }}>
            {projects.length}
          </p>
          <p className="text-xs opacity-60">{tg("statTotal")}</p>
        </div>
        <div className="p-4 text-center">
          <p className="text-2xl font-semibold text-success" style={{ fontFamily: "var(--font-heading)" }}>
            {activeCount}
          </p>
          <p className="text-xs opacity-60">{tg("statActive")}</p>
        </div>
        <div className="p-4 text-center">
          <p className="text-2xl font-semibold text-info" style={{ fontFamily: "var(--font-heading)" }}>
            {upcomingCount}
          </p>
          <p className="text-xs opacity-60">{tg("statUpcoming")}</p>
        </div>
      </div>

      <section>
        {projects.length > 0 ? (
          <ProjectList projects={projects} labels={projectLabels} />
        ) : (
          <EmptyState icon="🤝" message={t("sections.emptyProjects")} />
        )}
      </section>

      <div className="rounded-md border-l-4 border-(--border-neutral) bg-bg-card p-4 text-sm opacity-80">
        {tg("backOfficeHint")}
      </div>
    </div>
  );
}
