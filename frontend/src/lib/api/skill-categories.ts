import { apiFetch, extractCollection, pickLocalized } from "./client";
import type { SkillCategory } from "@/lib/types";

interface RawSkillCategory extends SkillCategory {
  nameEn?: string | null;
}

/**
 * Liste des catégories seules (sans leurs compétences — la relation inverse
 * `SkillCategory::$skill` reste `api_admin` uniquement, pour éviter un cycle
 * de sérialisation). Le groupement par catégorie ne passe pas par cette
 * fonction : `getSkills()` (src/lib/api/skills.ts) renvoie directement
 * chaque compétence avec sa catégorie imbriquée (`skill.skillCategory`),
 * c'est à partir de là que `SkillsByCategory` reconstruit les groupes — ça
 * évite d'afficher une catégorie vide comme celles sans compétence publiée.
 *
 * `locale` explicite — cf. commentaire dans projects.ts (bug de mémoïsation de `getLocale()`).
 */
export async function getSkillCategories(locale: string): Promise<SkillCategory[]> {
  const payload = await apiFetch<unknown>("/skill_categories", {
    tags: ["skill-categories"],
  });
  return extractCollection<RawSkillCategory>(payload).map(({ nameEn, ...category }) => ({
    ...category,
    name: pickLocalized(category.name, nameEn, locale),
  }));
}
