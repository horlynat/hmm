import type { Project, ProjectStatus } from "@/lib/types";

/**
 * Priorité d'affichage par défaut : les projets terminés d'abord (la vitrine
 * doit montrer du travail livré), puis ceux en cours en complément s'il n'y
 * en a pas assez, puis le reste — dans chaque groupe, du plus récent au plus
 * ancien. L'API ne renvoie pas de date exposée publiquement (`createdAt` est
 * réservé à `api_admin`), `id` (auto-incrémenté) sert de proxy fiable.
 */
const STATUS_PRIORITY: Record<ProjectStatus, number> = {
  termine: 0,
  en_cours: 1,
  collaboration: 2,
  a_venir: 3,
  suspendu: 4,
};

export function sortProjectsForDisplay(projects: Project[]): Project[] {
  return [...projects].sort((a, b) => {
    const priorityDiff = STATUS_PRIORITY[a.status] - STATUS_PRIORITY[b.status];
    return priorityDiff !== 0 ? priorityDiff : b.id - a.id;
  });
}
