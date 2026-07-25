import { apiFetch, extractCollection } from "./client";
import type { Course } from "@/lib/types";

export async function getCourses(): Promise<Course[]> {
  const payload = await apiFetch<unknown>("/courses", { tags: ["courses"] });
  return extractCollection<Course>(payload);
}
