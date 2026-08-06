import { apiFetch, extractCollection, pickLocalized } from "./client";
import type { Experience } from "@/lib/types";

interface RawExperience extends Experience {
  roleEn?: string | null;
  descriptionEn?: string | null;
}

/** `locale` explicite — cf. commentaire dans projects.ts (bug de mémoïsation de `getLocale()`). */
export async function getExperiences(locale: string): Promise<Experience[]> {
  const payload = await apiFetch<unknown>("/experiences", {
    tags: ["experiences"],
  });
  return extractCollection<RawExperience>(payload).map(({ roleEn, descriptionEn, ...experience }) => ({
    ...experience,
    role: pickLocalized(experience.role, roleEn, locale),
    description: pickLocalized(experience.description, descriptionEn, locale),
  }));
}
