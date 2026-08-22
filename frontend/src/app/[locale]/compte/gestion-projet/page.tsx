import { getTranslations } from "next-intl/server";
import { Briefcase, FolderKanban, Handshake, Activity, CalendarClock, UserRoundCheck } from "lucide-react";
import { Alert, Badge, ButtonLink, EmptyState, PageHeader, StatCard } from "@/components/ui";
import { ProjectList } from "@/components/sections/AccountLists";
import { redirect } from "@/i18n/navigation";
import { getCurrentUser } from "@/lib/auth/session";
import { FREELANCE_PROFILE_FIELD_LABEL_KEYS, type FreelanceProfileFieldKey } from "@/lib/profileFields";

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
  const tp = await getTranslations({ locale, namespace: "auth.profile" });

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
      <PageHeader icon={Briefcase} title={tg("title")} subtitle={tg("subtitle")} />

      {user.profileCompletion < 100 && (
        <Alert
          variant="warning"
          icon={UserRoundCheck}
          title={tg("profileBanner.title")}
          action={<ButtonLink href="/compte/profil">{tg("profileBanner.cta")}</ButtonLink>}
        >
          <p>{tg("profileBanner.body", { percent: user.profileCompletion })}</p>
          {user.missingProfileFields.length > 0 && (
            <div className="mt-2.5 flex flex-wrap gap-1.5">
              {user.missingProfileFields.map((field) => (
                <Badge key={field} variant="warning">
                  {tp(FREELANCE_PROFILE_FIELD_LABEL_KEYS[field as FreelanceProfileFieldKey] ?? field)}
                </Badge>
              ))}
            </div>
          )}
        </Alert>
      )}

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <StatCard icon={FolderKanban} label={tg("statTotal")} value={projects.length} />
        <StatCard icon={Activity} label={tg("statActive")} value={activeCount} tone="success" />
        <StatCard icon={CalendarClock} label={tg("statUpcoming")} value={upcomingCount} />
      </div>

      <section>
        {projects.length > 0 ? (
          <ProjectList projects={projects} labels={projectLabels} hrefPattern="/compte/gestion-projet/[id]" />
        ) : (
          <EmptyState icon={Handshake} message={t("sections.emptyProjects")} />
        )}
      </section>
    </div>
  );
}
