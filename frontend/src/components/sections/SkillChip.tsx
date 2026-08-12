import type { Skill } from "@/lib/types";

/**
 * Une compétence + son niveau (1 à 10), rendue comme une jauge dégradée
 * plutôt que les 10 points individuels de l'ancien rendu — réutilise
 * exactement le motif déjà en place ailleurs dans le projet pour ce type de
 * barre (`AccountLists.tsx` → progression d'un projet) : piste
 * `bg-brand-light`, remplissage `bg-gradient-to-r from-brand-primary
 * to-brand-accent` animé en `scaleX()` (pas de reflow, contrairement à un
 * `width` animé) plutôt qu'une nouvelle convention visuelle isolée.
 *
 * Accessibilité — l'ancien rendu (10 points `aria-hidden="true"` sans
 * aucune alternative texte) ne donnait AUCUNE information de niveau à un
 * lecteur d'écran. Ici la jauge porte `role="progressbar"` avec les bornes
 * réelles du champ (`level` : 1 à 10, validé côté back) et un
 * `aria-valuetext` explicite ("Niveau X sur 10") plutôt qu'un pourcentage
 * qui serait moins parlant sur une échelle 1-10. Le niveau n'est jamais
 * porté par la seule couleur : la valeur chiffrée reste affichée en texte
 * réel à côté de la jauge (masquée aux lecteurs d'écran pour éviter une
 * double annonce, l'info étant déjà dans `aria-valuetext`).
 */
export function SkillChip({ skill }: { skill: Skill }) {
  const level = Math.min(10, Math.max(1, skill.level));

  return (
    <div className="flex min-w-0 flex-col gap-2 rounded-[var(--radius-sm)] px-1 py-1.5">
      <div className="flex items-center justify-between gap-2">
        <span
          className="min-w-0 flex-1 truncate text-sm font-semibold"
          style={{ fontFamily: "var(--font-heading)" }}
          title={skill.name}
        >
          {skill.name}
        </span>
        <span
          className="shrink-0 font-mono text-[0.68rem] font-semibold tabular-nums text-(--color-muted)"
          aria-hidden="true"
        >
          {level}
          <span className="opacity-60">/10</span>
        </span>
      </div>
      <div
        role="progressbar"
        aria-label={skill.name}
        aria-valuemin={1}
        aria-valuemax={10}
        aria-valuenow={level}
        aria-valuetext={`Niveau ${level} sur 10`}
        className="h-1.5 w-full overflow-hidden rounded-full bg-brand-light"
      >
        <div
          className="h-full w-full origin-left rounded-full bg-gradient-to-r from-brand-primary to-brand-accent transition-transform duration-300"
          style={{ transform: `scaleX(${level / 10})` }}
        />
      </div>
    </div>
  );
}
