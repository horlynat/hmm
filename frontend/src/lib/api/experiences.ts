import { apiFetch, extractCollection } from "./client";
import type { Experience } from "@/lib/types";

export async function getExperiences(): Promise<Experience[]> {
  const payload = await apiFetch<unknown>("/experiences", {
    tags: ["experiences"],
  });
  return extractCollection<Experience>(payload);
}
