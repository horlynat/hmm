import "server-only";
import { apiFetch, extractCollection, pickLocalized } from "./client";
import type { Experience } from "@/lib/types";

interface RawExperience extends Experience {
  roleEn?: string | null;
  descriptionEn?: string | null;
}

/** `locale` explicite — cf. commentaire dans projects.ts (bug de mémoïsation de `getLocale()`). */
/**
 * `strict` : une API injoignable lève une erreur au lieu de renvoyer une liste
 * vide — pour les pages qui ne doivent jamais être mises en cache amputées
 * (cf. SiteAbout). Sans `strict`, comportement tolérant inchangé.
 */
export async function getExperiences(locale: string, options: { strict?: boolean } = {}): Promise<Experience[]> {
  const payload = await apiFetch<unknown>("/experiences", {
    tags: ["experiences"],
  });
  if (payload === null && options.strict) throw new Error("API /experiences indisponible.");
  return extractCollection<RawExperience>(payload).map(({ roleEn, descriptionEn, ...experience }) => ({
    ...experience,
    role: pickLocalized(experience.role, roleEn, locale),
    description: pickLocalized(experience.description, descriptionEn, locale),
  }));
}
