import { SkillAccordionList } from "./SkillAccordionList";
import { normalizeCategoryName } from "@/lib/skills/normalizeCategoryName";
import type { Skill } from "@/lib/types";

interface CategoryGroup {
  id: number;
  name: string;
  skills: Skill[];
}

function groupByCategory(skills: Skill[]): CategoryGroup[] {
  const groups = new Map<number, CategoryGroup>();

  for (const skill of skills) {
    const { id, name } = skill.skillCategory;
    const group = groups.get(id) ?? { id, name, skills: [] };
    group.skills.push(skill);
    groups.set(id, group);
  }

  return Array.from(groups.values())
    .map((group) => ({ ...group, skills: group.skills.slice().sort((a, b) => b.level - a.level) }))
    .sort((a, b) => b.skills.length - a.skills.length || a.name.localeCompare(b.name));
}

/**
 * Sélectionne et ordonne un sous-ensemble de catégories d'après une liste de
 * "slots" (chacun listant les libellés acceptés, fr + en) — remplace le tri
 * par nombre de compétences quand fourni. Un slot sans correspondance dans
 * les données reçues est simplement omis (même logique de dégradation que
 * le reste du composant : catégorie absente = pas d'erreur).
 */
function pickFeatured(grouped: CategoryGroup[], featuredCategories: string[][]): CategoryGroup[] {
  const byNormalizedName = new Map<string, CategoryGroup>();
  for (const group of grouped) {
    byNormalizedName.set(normalizeCategoryName(group.name), group);
  }

  const result: CategoryGroup[] = [];
  for (const acceptedLabels of featuredCategories) {
    const match = acceptedLabels
      .map(normalizeCategoryName)
      .map((label) => byNormalizedName.get(label))
      .find((group): group is CategoryGroup => group !== undefined);
    if (match) result.push(match);
  }
  return result;
}

interface SkillsByCategoryProps {
  skills: Skill[];
  /** Tronque chaque catégorie aux `n` compétences les mieux notées — pour un aperçu condensé (home). */
  maxSkillsPerCategory?: number;
  /**
   * Limite aux `n` catégories comptant le plus de compétences — pour un
   * aperçu condensé générique. Ignoré si `featuredCategories` est fourni.
   */
  maxCategories?: number;
  /**
   * Sélection et ordre éditoriaux explicites (ex. "catégories phares" de la
   * home) — prend le pas sur `maxCategories`. Chaque élément liste les
   * libellés acceptés (fr + en) pour ce slot ; cf. `lib/skills/featuredCategories.ts`.
   */
  featuredCategories?: string[][];
  /** "immersive" = traitement plus marqué (page dédiée /competences) ; "default" = sobre (home, /a-propos). */
  variant?: "default" | "immersive";
}

/**
 * Regroupe les compétences par catégorie (App\Entity\SkillCategory côté
 * back) plutôt qu'une liste à plat — une catégorie sans compétence publiée
 * n'apparaît simplement pas (dérivé des `skills` reçus, pas d'appel séparé
 * à `getSkillCategories()`). Rendu en accordéon (cf. `SkillAccordionList`) :
 * cette partie reste un Server Component (dérivation pure des données), seule
 * la coquille interactive (ouverture/fermeture) est envoyée au client.
 */
export function SkillsByCategory({
  skills,
  maxSkillsPerCategory,
  maxCategories,
  featuredCategories,
  variant = "default",
}: SkillsByCategoryProps) {
  const grouped = groupByCategory(skills);
  const categories = featuredCategories
    ? pickFeatured(grouped, featuredCategories)
    : maxCategories
      ? grouped.slice(0, maxCategories)
      : grouped;

  if (categories.length === 0) return null;

  const truncated = maxSkillsPerCategory
    ? categories.map((category) => ({ ...category, skills: category.skills.slice(0, maxSkillsPerCategory) }))
    : categories;

  return <SkillAccordionList categories={truncated} variant={variant} />;
}
