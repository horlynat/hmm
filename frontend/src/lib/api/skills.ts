import { apiFetch, extractCollection } from "./client";
import type { Skill } from "@/lib/types";

export async function getSkills(): Promise<Skill[]> {
  const payload = await apiFetch<unknown>("/skills", { tags: ["skills"] });
  return extractCollection<Skill>(payload);
}
