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
