import { getTranslations } from "next-intl/server";
import { Badge, ButtonLink, Card } from "@/components/ui";
import { getCurrentUser, getMyProject } from "@/lib/auth/session";
import type { SessionProjectDetail } from "@/lib/types";
import { projectStatusVariant } from "@/lib/status";

/**
 * Volontairement sans montants pour l'instant : `Project.budget`/`spent` sont
 * des chiffres de gestion INTERNE (comment le budget alloué est consommé côté
 * prestataire), pas ce que le client a lui-même payé — les afficher ici
 * fuitait des données financières internes vers le client. En attendant un
 * vrai modèle de paiement (comptant / acompte / abonnement), cette carte se
 * limite au statut du projet.
 */
function InvoiceCard({
  project,
  labels,
}: {
  project: SessionProjectDetail;
  labels: {
    deadline: string;
    noDeadline: string;
    viewProject: string;
  };
}) {
  return (
    <Card variant="soft" className="p-5">
      <div className="mb-3 flex items-start justify-between gap-2">
        <div>
          <span className="font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
            {project.title}
          </span>
          <p className="mt-0.5 text-xs opacity-60">
            {labels.deadline}:{" "}
            {project.deadline ? new Date(project.deadline).toLocaleDateString() : labels.noDeadline}
          </p>
        </div>
        <Badge variant={projectStatusVariant(project.status)}>{project.statusLabel}</Badge>
      </div>

      <ButtonLink
        href={{ pathname: "/compte/projets/[id]", params: { id: String(project.id) } }}
        variant="secondary"
        className="mt-2 w-fit text-xs"
      >
        {labels.viewProject}
      </ButtonLink>
    </Card>
  );
}

export default async function FacturesPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const user = await getCurrentUser();
  if (!user) return null;

  const t = await getTranslations({ locale, namespace: "auth.account" });
  const tf = await getTranslations({ locale, namespace: "auth.invoices" });

  const activeClientProjects = user.attributions.clientProjects.filter((p) => p.status === "en_cours");
  const details = (
    await Promise.all(activeClientProjects.map((p) => getMyProject(p.id)))
  ).filter((p): p is SessionProjectDetail => p !== null);

  const labels = {
    deadline: t("project.deadline"),
    noDeadline: t("project.noDeadline"),
    viewProject: tf("viewProject"),
  };

  return (
    <div className="max-w-190 space-y-6">
      <div>
        <h1 className="mb-2 text-[clamp(1.6rem,3vw,2.2rem)]">{tf("title")}</h1>
        <p className="opacity-70">{tf("subtitle")}</p>
      </div>

      <div className="rounded-md border-l-4 border-(--border-neutral) bg-bg-card p-4 text-sm opacity-80">
        {tf("comingSoonNote")}
      </div>

      {details.length > 0 ? (
        <div className="space-y-4">
          {details.map((project) => (
            <InvoiceCard key={project.id} project={project} labels={labels} />
          ))}
        </div>
      ) : (
        <Card variant="soft" className="p-6 text-sm opacity-70">
          {tf("empty")}
        </Card>
      )}
    </div>
  );
}
