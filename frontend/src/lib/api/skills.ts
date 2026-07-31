import { apiFetch, extractCollection, pickLocalized } from "./client";
import type { Skill } from "@/lib/types";

interface RawSkill extends Skill {
  nameEn?: string | null;
}

/** `locale` explicite — cf. commentaire dans projects.ts (bug de mémoïsation de `getLocale()`). */
export async function getSkills(locale: string): Promise<Skill[]> {
  const payload = await apiFetch<unknown>("/skills", { tags: ["skills"] });
  return extractCollection<RawSkill>(payload).map(({ nameEn, ...skill }) => ({
    ...skill,
    name: pickLocalized(skill.name, nameEn, locale),
  }));
}
