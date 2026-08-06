import { apiFetch, extractCollection, pickLocalized } from "./client";
import type { Testimonial } from "@/lib/types";

// Contenu curé manuellement, mis à jour rarement — la fraîcheur réelle vient
// du webhook /api/revalidate (revalidateTag) déclenché par le backend ; ce
// délai n'est qu'un filet de sécurité si ce webhook venait à échouer.
const TESTIMONIALS_REVALIDATE_SECONDS = 60 * 60 * 24;

interface RawTestimonial extends Testimonial {
  contentEn?: string | null;
}

/** `locale` explicite — cf. commentaire dans projects.ts (bug de mémoïsation de `getLocale()`). */
export async function getTestimonials(locale: string): Promise<Testimonial[]> {
  const payload = await apiFetch<unknown>("/testimonials", {
    tags: ["testimonials"],
    revalidate: TESTIMONIALS_REVALIDATE_SECONDS,
  });
  return extractCollection<RawTestimonial>(payload).map(({ contentEn, ...testimonial }) => ({
    ...testimonial,
    content: pickLocalized(testimonial.content, contentEn, locale),
  }));
}
