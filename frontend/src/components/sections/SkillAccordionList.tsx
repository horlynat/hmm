"use client";

import { useId, useState } from "react";
import clsx from "clsx";
import { ChevronDown } from "lucide-react";
import { Badge, Reveal } from "@/components/ui";
import { SkillChip } from "./SkillChip";
import type { Skill } from "@/lib/types";

interface CategoryGroup {
  id: number;
  name: string;
  skills: Skill[];
}

interface SkillAccordionListProps {
  categories: CategoryGroup[];
  /** "immersive" = traitement plus marqué (page dédiée /competences) ; "default" = sobre (home, /a-propos). */
  variant?: "default" | "immersive";
}

/**
 * Liste de catégories en accordéon (une catégorie = un en-tête cliquable,
 * ses compétences apparaissent dans le panneau) — remplace la grille de
 * cartes statiques précédente. Plusieurs catégories peuvent être ouvertes
 * en même temps (pas de fermeture forcée des autres à l'ouverture d'une
 * nouvelle) : moins surprenant qu'un accordéon à ouverture exclusive quand
 * l'utilisateur compare plusieurs catégories.
 *
 * Composant client (état d'ouverture) séparé de `SkillsByCategory` (regroupement/
 * filtrage, qui reste côté serveur) — seule la coquille interactive est
 * envoyée au client, pas la logique de dérivation des données.
 */
export function SkillAccordionList({ categories, variant = "default" }: SkillAccordionListProps) {
  const [openIds, setOpenIds] = useState<ReadonlySet<number>>(
    () => new Set(categories[0] ? [categories[0].id] : []),
  );

  function toggle(id: number) {
    setOpenIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  }

  return (
    <div
      className={clsx(
        "grid grid-cols-1 items-start gap-3 md:grid-cols-2",
        variant === "immersive" && "gap-4",
      )}
    >
      {categories.map((category, i) => (
        <Reveal key={category.id} delay={i * 0.05}>
          <SkillAccordionItem
            category={category}
            isOpen={openIds.has(category.id)}
            onToggle={() => toggle(category.id)}
            variant={variant}
          />
        </Reveal>
      ))}
    </div>
  );
}

function SkillAccordionItem({
  category,
  isOpen,
  onToggle,
  variant,
}: {
  category: CategoryGroup;
  isOpen: boolean;
  onToggle: () => void;
  variant: "default" | "immersive";
}) {
  const panelId = useId();
  const headingId = useId();
  const immersive = variant === "immersive";

  return (
    <div
      className={clsx(
        "@container overflow-hidden border bg-bg-card transition-colors duration-200",
        immersive ? "rounded-[var(--radius-lg)] shadow-sm" : "rounded-[var(--radius-md)]",
        isOpen ? "border-brand-accent/30" : "border-[var(--border-softer)] hover:border-brand-accent/30",
      )}
    >
      <h3 id={headingId} className="m-0">
        <button
          type="button"
          onClick={onToggle}
          aria-expanded={isOpen}
          aria-controls={panelId}
          className={clsx(
            "flex w-full items-center justify-between gap-3 text-left transition-colors hover:bg-brand-primary/[0.03]",
            immersive ? "px-4 py-4 sm:px-6 sm:py-5" : "px-4 py-3.5 sm:px-5 sm:py-4",
          )}
        >
          <span className="flex min-w-0 flex-1 items-center gap-2.5 sm:gap-3">
            <span
              className={clsx(
                "min-w-0 truncate font-semibold",
                immersive ? "text-base sm:text-lg" : "text-sm",
              )}
              style={{ fontFamily: "var(--font-heading)" }}
              title={category.name}
            >
              {category.name}
            </span>
            <Badge variant="neutral" className="shrink-0">
              {category.skills.length}
            </Badge>
          </span>
          <span
            className={clsx(
              "grid shrink-0 place-items-center rounded-full transition-all duration-300",
              immersive ? "h-8 w-8" : "h-6 w-6",
              isOpen && immersive && "bg-brand-primary/10 text-brand-primary",
            )}
          >
            <ChevronDown
              aria-hidden="true"
              size={immersive ? 18 : 16}
              className={clsx("transition-transform duration-300", isOpen && "rotate-180")}
            />
          </span>
        </button>
      </h3>

      {/* Animation d'ouverture en pur CSS (grid-template-rows 0fr → 1fr) :
          pas de mesure de hauteur en JS, fonctionne même avec un nombre de
          compétences variable par catégorie. `overflow-hidden` sur le
          conteneur interne masque le contenu pendant la transition — un
          `[transition-behavior:allow-discrete]`/`content-visibility`
          séparé n'est pas nécessaire ici. Neutralisé par la règle globale
          `prefers-reduced-motion` (transition-duration forcée à 0.01ms). */}
      <div
        id={panelId}
        role="region"
        aria-labelledby={headingId}
        className="grid transition-[grid-template-rows] duration-300 ease-out"
        style={{ gridTemplateRows: isOpen ? "1fr" : "0fr" }}
      >
        <div className="overflow-hidden">
          <div
            className={clsx(
              // `@sm:` (container query) plutôt que `sm:` (viewport) : une
              // carte occupe ~50% de la largeur dès `md:` (grille 2 colonnes
              // ci-dessus), un seuil basé sur le viewport la ferait passer en
              // 2 colonnes de compétences alors qu'elle n'a pas la place
              // réelle — la largeur mesurée de LA CARTE pilote la mise en
              // page, pas celle de l'écran.
              "grid grid-cols-1 gap-x-4 gap-y-1 border-t border-[var(--border-softer)] @sm:grid-cols-2 @sm:gap-x-6",
              immersive ? "px-4 pt-3 pb-4 sm:px-6 sm:pb-5" : "px-4 pt-2 pb-3.5 sm:px-5 sm:pb-4",
            )}
          >
            {category.skills.map((skill) => (
              <SkillChip key={skill.id} skill={skill} />
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}
