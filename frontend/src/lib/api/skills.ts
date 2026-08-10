import { apiFetch, extractCollection, pickLocalized } from "./client";
import type { Skill } from "@/lib/types";

interface RawSkillCategory {
  id: number;
  name: string;
  nameEn?: string | null;
}

interface RawSkill extends Omit<Skill, "skillCategory"> {
  nameEn?: string | null;
  skillCategory: RawSkillCategory;
}

function mapSkill(raw: RawSkill, locale: string): Skill {
  const { nameEn, skillCategory, ...skill } = raw;
  return {
    ...skill,
    name: pickLocalized(skill.name, nameEn, locale),
    skillCategory: {
      id: skillCategory.id,
      name: pickLocalized(skillCategory.name, skillCategory.nameEn, locale),
    },
  };
}

/** `locale` explicite — cf. commentaire dans projects.ts (bug de mémoïsation de `getLocale()`). */
export async function getSkills(locale: string): Promise<Skill[]> {
  const payload = await apiFetch<unknown>("/skills", { tags: ["skills"] });
  return extractCollection<RawSkill>(payload).map((raw) => mapSkill(raw, locale));
}

/**
 * Catégories mises en avant sur la home (aperçu condensé, cf.
 * `src/app/[locale]/(marketing)/page.tsx`) — noms canoniques (français, tels
 * qu'en base) pour rester corrects quel que soit le `locale` demandé : le
 * filtre porte sur `skillCategory.name` AVANT localisation, jamais sur le nom
 * déjà traduit (qui varie en anglais).
 */
const HOME_FEATURED_CATEGORY_NAMES = [
  "Assurances & Management des Risques",
  "Cybersécurité",
  "IA & Data Science",
  "Developpement Web Fullstack",
];

export async function getFeaturedSkills(locale: string): Promise<Skill[]> {
  const payload = await apiFetch<unknown>("/skills", { tags: ["skills"] });
  return extractCollection<RawSkill>(payload)
    .filter((raw) => HOME_FEATURED_CATEGORY_NAMES.includes(raw.skillCategory.name))
    .map((raw) => mapSkill(raw, locale));
}
