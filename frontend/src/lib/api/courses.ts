import "server-only";
import { apiFetch, extractCollection, pickLocalized } from "./client";
import type { Course } from "@/lib/types";

interface RawCourse extends Course {
  titleEn?: string | null;
  descriptionEn?: string | null;
}

/** `locale` explicite — cf. commentaire dans projects.ts (bug de mémoïsation de `getLocale()`). */
/**
 * `strict` : une API injoignable lève une erreur au lieu de renvoyer une liste
 * vide — pour les pages qui ne doivent jamais être mises en cache amputées
 * (cf. SiteAbout). Sans `strict`, comportement tolérant inchangé.
 */
export async function getCourses(locale: string, options: { strict?: boolean } = {}): Promise<Course[]> {
  const payload = await apiFetch<unknown>("/courses", { tags: ["courses"] });
  if (payload === null && options.strict) throw new Error("API /courses indisponible.");
  return extractCollection<RawCourse>(payload).map(({ titleEn, descriptionEn, ...course }) => ({
    ...course,
    title: pickLocalized(course.title, titleEn, locale),
    description: pickLocalized(course.description, descriptionEn, locale),
  }));
}
