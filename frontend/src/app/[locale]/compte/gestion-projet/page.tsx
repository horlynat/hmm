import { getTranslations } from "next-intl/server";
import { Briefcase, UserRoundCheck } from "lucide-react";
import { Alert, Badge, ButtonLink, PageHeader } from "@/components/ui";
import { AvailableProjectsTable } from "@/components/sections/AvailableProjectsTable";
import { MyProjectsTable } from "@/components/sections/MyProjectsTable";
import { JoinRequestList } from "@/components/sections/JoinRequestList";
import { ProjectHubTabs } from "@/components/sections/ProjectHubTabs";
import { redirect } from "@/i18n/navigation";
import { getCurrentUser, getAvailableProjects, getMyJoinRequests } from "@/lib/auth/session";
import { FREELANCE_PROFILE_FIELD_LABEL_KEYS, type FreelanceProfileFieldKey } from "@/lib/profileFields";

/**
 * Hub "Gestion de projet" — réservé aux freelances/collaborateurs
 * (isCollaborator), remplace les trois anciens liens d'aside distincts (Mes
 * projets / Gestion de projet / Projets disponibles, cf. AccountNav.tsx) par
 * une seule page à onglets internes (cf. la maquette de refonte validée) :
 * "Mes projets" (engagements actifs, client + collaboration confondus),
 * "Projets disponibles" (candidature) et "Mes demandes" (suivi des
 * candidatures envoyées). Les trois jeux de données sont chargés en
 * parallèle : chaque onglet reste un Server Component, seule la bascule
 * entre eux est côté client (ProjectHubTabs).
 */
export default async function GestionProjetPage({
  params,
  searchParams,
}: {
  params: Promise<{ locale: string }>;
  searchParams: Promise<{ tab?: string }>;
}) {
  const { locale } = await params;
  const { tab } = await searchParams;
  const user = await getCurrentUser();
  if (!user) return null;

  if (!user.isCollaborator) {
    redirect({ href: "/compte", locale });
  }

  const tg = await getTranslations({ locale, namespace: "auth.projectManagement" });
  const ta = await getTranslations({ locale, namespace: "auth.availableProjects" });
  const tp = await getTranslations({ locale, namespace: "auth.profile" });

  const profileComplete = user.profileCompletion >= 100;
  const [availableProjects, joinRequests] = await Promise.all([
    profileComplete ? getAvailableProjects() : Promise.resolve([]),
    getMyJoinRequests(),
  ]);
  const openProjects = availableProjects ?? [];
  const requests = joinRequests ?? [];

  const mineCount =
    user.attributions.clientProjects.length +
    user.attributions.collaboratingProjects.length +
    user.attributions.ownedProjects.length;

  const profileBanner = !profileComplete && (
    <Alert
      variant="warning"
      icon={UserRoundCheck}
      title={ta("profileBanner.title")}
      action={<ButtonLink href="/compte/profil">{ta("profileBanner.cta")}</ButtonLink>}
    >
      <p>{ta("profileBanner.body", { percent: user.profileCompletion })}</p>
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
  );

  return (
    <div className="space-y-8">
      <PageHeader icon={Briefcase} title={tg("title")} subtitle={tg("subtitle")} />

      <ProjectHubTabs
        initialTab={tab === "open" || tab === "requests" ? tab : "mine"}
        counts={{ mine: mineCount, open: openProjects.length, requests: requests.length }}
        mine={<MyProjectsTable user={user} locale={locale} />}
        open={
          <div className="space-y-4">
            {profileBanner}
            {profileComplete && <AvailableProjectsTable projects={openProjects} />}
          </div>
        }
        requests={<JoinRequestList requests={requests} locale={locale} />}
      />
    </div>
  );
}
