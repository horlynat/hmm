import { getTranslations } from "next-intl/server";
import { Rocket, UserRoundCheck } from "lucide-react";
import { Alert, Badge, ButtonLink, EmptyState, PageHeader } from "@/components/ui";
import { AvailableProjectList } from "@/components/sections/AvailableProjectList";
import { redirect } from "@/i18n/navigation";
import { getCurrentUser, getAvailableProjects } from "@/lib/auth/session";
import { FREELANCE_PROFILE_FIELD_LABEL_KEYS, type FreelanceProfileFieldKey } from "@/lib/profileFields";

/**
 * Espace "projets à venir" ouvert à tout freelance/collaborateur
 * (isCollaborator) au profil complet à 100 % — c'est ici qu'il se propose
 * pour un projet pas encore affecté à une équipe (POST /api/me/projects/{id}/join,
 * cf. AvailableProjectList). Même garde serveur que /compte/gestion-projet.
 * Contrairement à cette dernière, le profil incomplet bloque totalement
 * l'accès (pas de lecture seule) : le backend renvoie une liste vide dans ce
 * cas (App\Controller\Api\MeController::readAvailableProjects()), donc la
 * page n'affiche que le rappel de complétion, jamais une liste vide trompeuse.
 */
export default async function ProjetsDisponiblesPage({
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

  const t = await getTranslations({ locale, namespace: "auth.availableProjects" });
  const tp = await getTranslations({ locale, namespace: "auth.profile" });

  const profileComplete = user.profileCompletion >= 100;
  const projects = profileComplete ? ((await getAvailableProjects()) ?? []) : [];

  return (
    <div className="space-y-8">
      <PageHeader icon={Rocket} title={t("title")} subtitle={t("subtitle")} />

      {!profileComplete && (
        <Alert
          variant="warning"
          icon={UserRoundCheck}
          title={t("profileBanner.title")}
          action={<ButtonLink href="/compte/profil">{t("profileBanner.cta")}</ButtonLink>}
        >
          <p>{t("profileBanner.body", { percent: user.profileCompletion })}</p>
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

      {profileComplete && (
        <section>
          {projects.length > 0 ? (
            <AvailableProjectList projects={projects} />
          ) : (
            <EmptyState icon="🚀" message={t("empty")} />
          )}
        </section>
      )}
    </div>
  );
}
