import { apiFetch, extractCollection, pickLocalized } from "./client";
import type { Course } from "@/lib/types";

interface RawCourse extends Course {
  titleEn?: string | null;
  descriptionEn?: string | null;
}

/** `locale` explicite — cf. commentaire dans projects.ts (bug de mémoïsation de `getLocale()`). */
export async function getCourses(locale: string): Promise<Course[]> {
  const payload = await apiFetch<unknown>("/courses", { tags: ["courses"] });
  return extractCollection<RawCourse>(payload).map(({ titleEn, descriptionEn, ...course }) => ({
    ...course,
    title: pickLocalized(course.title, titleEn, locale),
    description: pickLocalized(course.description, descriptionEn, locale),
  }));
}
