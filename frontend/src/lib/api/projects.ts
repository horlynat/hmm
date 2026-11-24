import "server-only";
import { apiFetch, extractCollection, pickLocalized, pickLocalizedList } from "./client";
import type { Project, ProjectInfo } from "@/lib/types";

// Contenu curé manuellement, mis à jour rarement — la fraîcheur réelle vient
// du webhook /api/revalidate (revalidateTag) déclenché par le backend ; ce
// délai n'est qu'un filet de sécurité si ce webhook venait à échouer.
const PROJECTS_REVALIDATE_SECONDS = 60 * 60 * 24;

interface RawProjectInfo extends ProjectInfo {
  roleEn?: string | null;
  objectivesEn?: string[] | null;
  techStackEn?: ProjectInfo["techStack"] | null;
  challengesEn?: ProjectInfo["challenges"] | null;
  resultsEn?: ProjectInfo["results"] | null;
  resultEn?: string | null;
  contextEn?: string | null;
  stakeEn?: string | null;
  solutionEn?: string | null;
  briefEn?: ProjectInfo["brief"] | null;
  stepsEn?: ProjectInfo["steps"] | null;
  deliverablesEn?: string[] | null;
  galleryCaptionsEn?: string[] | null;
}

/**
 * Listes du récit : seules les entrées bien formées sont gardées. Le
 * formulaire admin n'en produit pas d'autres, mais une écriture API forgée
 * (`brief: [null]`, titre objet…) ne doit jamais faire tomber la page.
 */
function pairList(value: unknown): { title: string; text: string | null }[] {
  if (!Array.isArray(value)) return [];
  return value.flatMap((item) => {
    if (!item || typeof item !== "object") return [];
    const { title, text } = item as { title?: unknown; text?: unknown };
    if (typeof title !== "string" || title === "") return [];
    return [{ title, text: typeof text === "string" ? text : null }];
  });
}

function stringList(value: unknown): string[] {
  return Array.isArray(value) ? value.filter((v): v is string => typeof v === "string" && v !== "") : [];
}

function textOrNull(value: unknown): string | null {
  return typeof value === "string" && value !== "" ? value : null;
}

/** Champs ajoutés après coup : absents (ancienne API) ou null en base. */
type RawStory = Partial<Pick<ProjectInfo, "result" | "context" | "stake" | "solution" | "relatedOfferSlug" | "discreet">> & {
  brief?: ProjectInfo["brief"] | null;
  steps?: ProjectInfo["steps"] | null;
  deliverables?: string[] | null;
  galleryCaptions?: string[] | null;
};

interface RawProject extends Omit<Project, "info"> {
  titleEn?: string | null;
  descriptionEn?: string | null;
  info: RawProjectInfo | null;
}

function localizeProjectInfo(info: RawProjectInfo | null, locale: string): ProjectInfo | null {
  if (!info) return null;
  const {
    roleEn,
    objectivesEn,
    techStackEn,
    challengesEn,
    resultsEn,
    resultEn,
    contextEn,
    stakeEn,
    solutionEn,
    briefEn,
    stepsEn,
    deliverablesEn,
    galleryCaptionsEn,
    ...rest
  } = info;
  const story = info as RawStory;
  return {
    ...rest,
    result: pickLocalized(textOrNull(story.result), textOrNull(resultEn), locale),
    context: pickLocalized(textOrNull(story.context), textOrNull(contextEn), locale),
    stake: pickLocalized(textOrNull(story.stake), textOrNull(stakeEn), locale),
    solution: pickLocalized(textOrNull(story.solution), textOrNull(solutionEn), locale),
    brief: pickLocalizedList(pairList(story.brief), pairList(briefEn), locale),
    steps: pickLocalizedList(pairList(story.steps), pairList(stepsEn), locale),
    deliverables: pickLocalizedList(stringList(story.deliverables), stringList(deliverablesEn), locale),
    galleryCaptions: pickLocalizedList(stringList(story.galleryCaptions), stringList(galleryCaptionsEn), locale),
    discreet: story.discreet === true,
    relatedOfferSlug: textOrNull(story.relatedOfferSlug),
    role: pickLocalized(info.role, roleEn, locale),
    objectives: pickLocalizedList(info.objectives, objectivesEn, locale),
    techStack: pickLocalizedList(info.techStack, techStackEn, locale),
    challenges: pickLocalizedList(info.challenges, challengesEn, locale),
    results: pickLocalizedList(info.results, resultsEn, locale),
  };
}

/**
 * `locale` est un paramètre explicite (et non résolu via `getLocale()`) car
 * `getLocale()`/`getMessages()` sans argument sont mémoïsés par React
 * `cache()` sur une clé qui ignore la locale réelle (respectivement aucun
 * argument, et `opts?.locale` toujours `undefined` si non fourni). Pendant
 * `next build`, plusieurs pages de locales différentes peuvent partager le
 * même worker : la première locale résolue par ce worker se figeait alors
 * pour toutes les pages statiques suivantes qu'il générait (bug constaté :
 * les pages `/en/*` rendaient du contenu français). Toujours faire remonter
 * `locale` depuis `params` plutôt que de le résoudre ici.
 */
export async function getProjects(locale: string): Promise<Project[]> {
  const payload = await apiFetch<unknown>("/projects", {
    tags: ["projects"],
    revalidate: PROJECTS_REVALIDATE_SECONDS,
  });
  return extractCollection<RawProject>(payload).map(({ titleEn, descriptionEn, info, ...project }) => ({
    ...project,
    title: pickLocalized(project.title, titleEn, locale),
    description: pickLocalized(project.description, descriptionEn, locale),
    info: localizeProjectInfo(info, locale),
  }));
}

export async function getProjectBySlug(slug: string, locale: string): Promise<Project | null> {
  // Filtre sur la collection plutôt qu'un GET direct par slug : confirmé côté
  // backend (App\ApiResource\ProjectApiResource) que l'identifiant de la
  // ressource est `id`, pas `slug` — il n'existe pas de route /projects/{slug}.
  // Un vrai lookup direct nécessiterait un provider dédié côté API Platform.
  const projects = await getProjects(locale);
  return projects.find((project) => project.slug === slug) ?? null;
}

/**
 * Slugs uniquement, indépendant de la locale — pour `generateStaticParams`,
 * qui s'exécute au build sans contexte de requête. Les slugs sont
 * identiques quelle que soit la langue, donc aucune perte d'info ici.
 */
export async function getProjectSlugs(): Promise<string[]> {
  const payload = await apiFetch<unknown>("/projects", {
    tags: ["projects"],
    revalidate: PROJECTS_REVALIDATE_SECONDS,
  });
  return extractCollection<{ slug: string }>(payload).map((project) => project.slug);
}
