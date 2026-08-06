import { apiFetch, extractCollection, pickLocalized } from "./client";
import type { SkillCategory } from "@/lib/types";

interface RawSkillCategory extends SkillCategory {
  nameEn?: string | null;
}

/**
 * La relation Skill <-> SkillCategory n'est pas exposée dans le groupe
 * `api_public` (cf. plan) : cette liste ne permet donc pas de grouper les
 * compétences par catégorie pour l'instant, seulement d'afficher les noms de
 * catégorie si besoin ailleurs.
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
