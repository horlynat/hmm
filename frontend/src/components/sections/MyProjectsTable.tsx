import { getTranslations } from "next-intl/server";
import { FolderKanban } from "lucide-react";
import { Badge, Card, EmptyState } from "@/components/ui";
import { Link } from "@/i18n/navigation";
import { projectStatusVariant } from "@/lib/status";
import type { SessionUser, SessionProject } from "@/lib/types";

interface Row {
  project: SessionProject;
  role: string;
  href: "/compte/projets/[id]" | "/compte/gestion-projet/[id]";
}

/**
 * "Mes projets" du hub /compte/gestion-projet — table dense (mockup validé :
 * une seule table avec une colonne "Rôle" distinguant client/chef de
 * projet/collaborateur), pas une grille de cartes par catégorie comme sur
 * /compte/projets. Un projet où l'on est à la fois client et collaborateur
 * (cas réel, cf. compte/page.tsx) ne doit apparaître qu'une fois — priorité
 * donnée au rôle de collaboration, plus spécifique que "Client".
 */
export async function MyProjectsTable({ user, locale }: { user: SessionUser; locale: string }) {
  const t = await getTranslations({ locale, namespace: "auth.projectManagement" });
  const tp = await getTranslations({ locale, namespace: "auth.account" });
  const { attributions } = user;

  const seen = new Set<number>();
  const rows: Row[] = [];
  for (const p of attributions.ownedProjects) {
    if (seen.has(p.id)) continue;
    seen.add(p.id);
    rows.push({ project: p, role: t("roles.owner"), href: "/compte/gestion-projet/[id]" });
  }
  for (const p of attributions.collaboratingProjects) {
    if (seen.has(p.id)) continue;
    seen.add(p.id);
    rows.push({ project: p, role: t("roles.collaborator"), href: "/compte/gestion-projet/[id]" });
  }
  for (const p of attributions.clientProjects) {
    if (seen.has(p.id)) continue;
    seen.add(p.id);
    rows.push({ project: p, role: t("roles.client"), href: "/compte/projets/[id]" });
  }

  if (rows.length === 0) {
    return <EmptyState icon={FolderKanban} message={tp("sections.emptyProjects")} />;
  }

  return (
    <Card variant="soft" className="overflow-hidden p-0">
      <div className="overflow-x-auto">
        <table className="w-full text-left text-sm">
          <thead>
            <tr className="text-[10.5px] font-bold uppercase tracking-wide text-(--color-muted)">
              <th className="px-4 py-3 font-bold">{t("mineColumns.project")}</th>
              <th className="px-4 py-3 font-bold">{t("mineColumns.role")}</th>
              <th className="px-4 py-3 font-bold">{t("mineColumns.progress")}</th>
              <th className="px-4 py-3 font-bold">{t("mineColumns.deadline")}</th>
              <th className="px-4 py-3 font-bold">{t("mineColumns.status")}</th>
            </tr>
          </thead>
          <tbody>
            {rows.map(({ project, role, href }) => (
              <tr key={project.id} className="border-t border-(--border-neutral)">
                <td className="px-4 py-3">
                  <Link
                    href={{ pathname: href, params: { id: String(project.id) } }}
                    className="font-semibold hover:text-brand-primary hover:underline"
                  >
                    {project.title}
                  </Link>
                </td>
                <td className="px-4 py-3 text-(--color-muted)">{role}</td>
                <td className="px-4 py-3">
                  <div className="flex min-w-[120px] items-center gap-2">
                    <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-(--color-surface-muted)">
                      <div className="h-full rounded-full bg-brand-primary" style={{ width: `${project.progress}%` }} />
                    </div>
                    <span className="shrink-0 text-xs font-semibold text-(--color-muted)">{project.progress}%</span>
                  </div>
                </td>
                <td className="px-4 py-3 text-xs text-(--color-muted)">
                  {project.deadline ? new Date(project.deadline).toLocaleDateString(locale, { day: "numeric", month: "short" }) : "—"}
                </td>
                <td className="px-4 py-3">
                  <Badge variant={projectStatusVariant(project.status)}>{project.statusLabel}</Badge>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </Card>
  );
}
