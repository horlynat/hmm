import { apiFetch, extractCollection } from "./client";
import type { SkillCategory } from "@/lib/types";

/**
 * La relation Skill <-> SkillCategory n'est pas exposée dans le groupe
 * `api_public` (cf. plan) : cette liste ne permet donc pas de grouper les
 * compétences par catégorie pour l'instant, seulement d'afficher les noms de
 * catégorie si besoin ailleurs.
 */
export async function getSkillCategories(): Promise<SkillCategory[]> {
  const payload = await apiFetch<unknown>("/skill_categories", {
    tags: ["skill-categories"],
  });
  return extractCollection<SkillCategory>(payload);
}
