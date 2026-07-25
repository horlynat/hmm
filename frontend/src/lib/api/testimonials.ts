import { apiFetch, extractCollection } from "./client";
import type { Testimonial } from "@/lib/types";

export async function getTestimonials(): Promise<Testimonial[]> {
  const payload = await apiFetch<unknown>("/testimonials", {
    tags: ["testimonials"],
  });
  return extractCollection<Testimonial>(payload);
}
