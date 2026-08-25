/**
 * Noms `view-transition-name` partagés entre une carte (liste) et l'image de
 * couverture de sa page de détail — doivent être identiques des deux côtés
 * pour que l'API View Transitions fasse "morphir" l'une vers l'autre au lieu
 * d'un simple fondu. Centralisé ici plutôt que dupliqué dans chaque fichier
 * (ArticleCard/ProjectCard, blog/[slug], realisations/[slug], NextUpCard) —
 * un seul format à faire évoluer, aucun risque de divergence silencieuse.
 */
export function articleImageTransitionName(articleId: number): string {
  return `article-image-${articleId}`;
}

export function projectImageTransitionName(projectId: number): string {
  return `project-image-${projectId}`;
}
