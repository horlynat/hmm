import "server-only";
import { apiFetch, extractCollection, pickLocalized } from "./client";
import { getMediaUrl } from "@/lib/media";
import type { AboutContent, AboutJourneyStat } from "@/lib/types";

// Contenu curé manuellement, mis à jour rarement — la fraîcheur réelle vient
// du webhook /api/revalidate (revalidateTag) déclenché par le backend ; ce
// délai n'est qu'un filet de sécurité si ce webhook venait à échouer.
const ABOUT_CONTENT_REVALIDATE_SECONDS = 60 * 60 * 24;

interface RawAboutContent extends Omit<
  AboutContent,
  | "visionPillar"
  | "missionPillar"
  | "ambitionPillar"
  | "horizonMidText"
  | "learningText"
  | "journeyStats"
  | "why5Title"
  | "why5Desc"
  | "skillsTitle"
  | "skillsLede"
  | "skillsNote"
  | "projectsTitle"
  | "projectsLede"
  | "pathTitle"
  | "pathLede"
> {
  heroTitleEn?: string | null;
  heroTitleAccentEn?: string | null;
  heroSubEn?: string | null;
  profileRoleEn?: string | null;
  profileAvailabilityEn?: string | null;
  bioTitleEn?: string | null;
  bioP1En?: string | null;
  bioP2En?: string | null;
  visionLedeEn?: string | null;
  visionTodayTextEn?: string | null;
  visionTomorrowTextEn?: string | null;
  why1TitleEn?: string | null;
  why1DescEn?: string | null;
  why2TitleEn?: string | null;
  why2DescEn?: string | null;
  why3TitleEn?: string | null;
  why3DescEn?: string | null;
  why4TitleEn?: string | null;
  why4DescEn?: string | null;
  ctaTitleEn?: string | null;
  ctaSubEn?: string | null;
  // Refonte R5 : optionnels ici aussi — un backend antérieur à la migration
  // (déploiements séparés) ne les renvoie pas encore.
  why5Title?: string | null;
  why5TitleEn?: string | null;
  why5Desc?: string | null;
  why5DescEn?: string | null;
  visionPillar?: string | null;
  visionPillarEn?: string | null;
  missionPillar?: string | null;
  missionPillarEn?: string | null;
  ambitionPillar?: string | null;
  ambitionPillarEn?: string | null;
  horizonMidText?: string | null;
  horizonMidTextEn?: string | null;
  learningText?: string | null;
  learningTextEn?: string | null;
  skillsTitle?: string | null;
  skillsTitleEn?: string | null;
  skillsLede?: string | null;
  skillsLedeEn?: string | null;
  skillsNote?: string | null;
  skillsNoteEn?: string | null;
  projectsTitle?: string | null;
  projectsTitleEn?: string | null;
  projectsLede?: string | null;
  projectsLedeEn?: string | null;
  pathTitle?: string | null;
  pathTitleEn?: string | null;
  pathLede?: string | null;
  pathLedeEn?: string | null;
  journeyStats?: unknown;
  journeyStatsEn?: unknown;
}

/**
 * Repères du parcours : ne garde que des paires {value, label} de chaînes non
 * vides — la donnée vient de l'API, rien n'est supposé de sa forme.
 */
function toJourneyStats(value: unknown): AboutJourneyStat[] {
  if (!Array.isArray(value)) return [];
  return value.flatMap((row) => {
    if (typeof row !== "object" || row === null) return [];
    const { value: v, label } = row as Record<string, unknown>;
    return typeof v === "string" &&
      typeof label === "string" &&
      v.trim() !== "" &&
      label.trim() !== ""
      ? [{ value: v, label }]
      : [];
  });
}

/** Ligne vide : base sans contenu « À propos » encore saisi. */
const EMPTY_ROW = Object.fromEntries(
  [
    "heroTitle",
    "heroTitleAccent",
    "heroSub",
    "profileName",
    "profileRole",
    "profileAvailability",
    "bioTitle",
    "bioP1",
    "bioP2",
    "visionLede",
    "visionTodayText",
    "visionTomorrowText",
    "why1Title",
    "why1Desc",
    "why2Title",
    "why2Desc",
    "why3Title",
    "why3Desc",
    "why4Title",
    "why4Desc",
    "ctaTitle",
    "ctaSub",
  ].map((k) => [k, ""]),
) as unknown as RawAboutContent;

/** `locale` explicite — cf. commentaire dans projects.ts (bug de mémoïsation de `getLocale()`). */
export async function getAboutContent(
  locale: string,
): Promise<AboutContent | null> {
  const payload = await apiFetch<unknown>("/about_contents", {
    tags: ["about-content"],
    revalidate: ABOUT_CONTENT_REVALIDATE_SECONDS,
  });
  // null = API injoignable (l'appelant refuse alors de régénérer la page) ;
  // aucune ligne en base (environnement neuf) = contenu vide, page sans textes.
  if (payload === null) return null;
  const raw = extractCollection<RawAboutContent>(payload)[0] ?? EMPTY_ROW;

  return {
    heroTitle: pickLocalized(raw.heroTitle, raw.heroTitleEn, locale),
    heroTitleAccent: pickLocalized(
      raw.heroTitleAccent,
      raw.heroTitleAccentEn,
      locale,
    ),
    heroSub: pickLocalized(raw.heroSub, raw.heroSubEn, locale),
    profileName: raw.profileName,
    profileRole: pickLocalized(raw.profileRole, raw.profileRoleEn, locale),
    profileAvailability: pickLocalized(
      raw.profileAvailability,
      raw.profileAvailabilityEn,
      locale,
    ),
    profileImagePath: raw.profileImagePath
      ? getMediaUrl(raw.profileImagePath)
      : null,
    bioTitle: pickLocalized(raw.bioTitle, raw.bioTitleEn, locale),
    bioP1: pickLocalized(raw.bioP1, raw.bioP1En, locale),
    bioP2: pickLocalized(raw.bioP2, raw.bioP2En, locale),
    visionLede: pickLocalized(raw.visionLede, raw.visionLedeEn, locale),
    visionTodayText: pickLocalized(
      raw.visionTodayText,
      raw.visionTodayTextEn,
      locale,
    ),
    visionTomorrowText: pickLocalized(
      raw.visionTomorrowText,
      raw.visionTomorrowTextEn,
      locale,
    ),
    why1Title: pickLocalized(raw.why1Title, raw.why1TitleEn, locale),
    why1Desc: pickLocalized(raw.why1Desc, raw.why1DescEn, locale),
    why2Title: pickLocalized(raw.why2Title, raw.why2TitleEn, locale),
    why2Desc: pickLocalized(raw.why2Desc, raw.why2DescEn, locale),
    why3Title: pickLocalized(raw.why3Title, raw.why3TitleEn, locale),
    why3Desc: pickLocalized(raw.why3Desc, raw.why3DescEn, locale),
    why4Title: pickLocalized(raw.why4Title, raw.why4TitleEn, locale),
    why4Desc: pickLocalized(raw.why4Desc, raw.why4DescEn, locale),
    why5Title: pickLocalized(raw.why5Title ?? "", raw.why5TitleEn, locale),
    why5Desc: pickLocalized(raw.why5Desc ?? "", raw.why5DescEn, locale),
    ctaTitle: pickLocalized(raw.ctaTitle, raw.ctaTitleEn, locale),
    ctaSub: pickLocalized(raw.ctaSub, raw.ctaSubEn, locale),
    visionPillar: pickLocalized(
      raw.visionPillar ?? "",
      raw.visionPillarEn,
      locale,
    ),
    missionPillar: pickLocalized(
      raw.missionPillar ?? "",
      raw.missionPillarEn,
      locale,
    ),
    ambitionPillar: pickLocalized(
      raw.ambitionPillar ?? "",
      raw.ambitionPillarEn,
      locale,
    ),
    horizonMidText: pickLocalized(
      raw.horizonMidText ?? "",
      raw.horizonMidTextEn,
      locale,
    ),
    learningText: pickLocalized(
      raw.learningText ?? "",
      raw.learningTextEn,
      locale,
    ),
    skillsTitle: pickLocalized(
      raw.skillsTitle ?? "",
      raw.skillsTitleEn,
      locale,
    ),
    skillsLede: pickLocalized(raw.skillsLede ?? "", raw.skillsLedeEn, locale),
    skillsNote: pickLocalized(raw.skillsNote ?? "", raw.skillsNoteEn, locale),
    projectsTitle: pickLocalized(
      raw.projectsTitle ?? "",
      raw.projectsTitleEn,
      locale,
    ),
    projectsLede: pickLocalized(
      raw.projectsLede ?? "",
      raw.projectsLedeEn,
      locale,
    ),
    pathTitle: pickLocalized(raw.pathTitle ?? "", raw.pathTitleEn, locale),
    pathLede: pickLocalized(raw.pathLede ?? "", raw.pathLedeEn, locale),
    // Liste anglaise retenue seulement si elle contient au moins un repère
    // valide ; sinon repli sur le français (même règle que pickLocalizedList).
    journeyStats: (() => {
      const fr = toJourneyStats(raw.journeyStats);
      const en = toJourneyStats(raw.journeyStatsEn);
      return locale === "en" && en.length > 0 ? en : fr;
    })(),
  };
}
