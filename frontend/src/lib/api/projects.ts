import { apiFetch, extractCollection } from "./client";
import type { Project } from "@/lib/types";

export async function getProjects(): Promise<Project[]> {
  const payload = await apiFetch<unknown>("/projects", { tags: ["projects"] });
  return extractCollection<Project>(payload);
}

export async function getProjectBySlug(slug: string): Promise<Project | null> {
  // Filtre sur la collection plutôt qu'un GET direct par slug : l'URI Template
  // exacte de l'opération Get (id vs slug) n'a pas pu être confirmée depuis
  // cette branche. À optimiser en GET direct une fois vérifié côté backend.
  const projects = await getProjects();
  return projects.find((project) => project.slug === slug) ?? null;
}
