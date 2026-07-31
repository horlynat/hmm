import { apiFetch, extractCollection, pickLocalized, pickLocalizedList } from "./client";
import type { AboutContent } from "@/lib/types";

// Contenu curé manuellement, mis à jour rarement — la fraîcheur réelle vient
// du webhook /api/revalidate (revalidateTag) déclenché par le backend ; ce
// délai n'est qu'un filet de sécurité si ce webhook venait à échouer.
const ABOUT_CONTENT_REVALIDATE_SECONDS = 60 * 60 * 24;

interface RawAboutContent extends AboutContent {
  heroEyebrowEn?: string | null;
  heroTitleEn?: string | null;
  heroTitleAccentEn?: string | null;
  heroSubEn?: string | null;
  profileRoleEn?: string | null;
  profileAvailabilityEn?: string | null;
  profileAlsoEn?: string | null;
  profileLocationEn?: string | null;
  profileWorkModeEn?: string | null;
  profileLanguagesEn?: string | null;
  bioTitleEn?: string | null;
  bioP1En?: string | null;
  bioP2En?: string | null;
  bioP3En?: string | null;
  visionTitleEn?: string | null;
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
  beyondLanguagesEn?: string[] | null;
  beyondInterestsEn?: string[] | null;
  ctaTitleEn?: string | null;
  ctaSubEn?: string | null;
}

/** `locale` explicite — cf. commentaire dans projects.ts (bug de mémoïsation de `getLocale()`). */
export async function getAboutContent(locale: string): Promise<AboutContent | null> {
  const payload = await apiFetch<unknown>("/about_contents", {
    tags: ["about-content"],
    revalidate: ABOUT_CONTENT_REVALIDATE_SECONDS,
  });
  const raw = extractCollection<RawAboutContent>(payload)[0];
  if (!raw) return null;

  return {
    heroEyebrow: pickLocalized(raw.heroEyebrow, raw.heroEyebrowEn, locale),
    heroTitle: pickLocalized(raw.heroTitle, raw.heroTitleEn, locale),
    heroTitleAccent: pickLocalized(raw.heroTitleAccent, raw.heroTitleAccentEn, locale),
    heroSub: pickLocalized(raw.heroSub, raw.heroSubEn, locale),
    profileName: raw.profileName,
    profileRole: pickLocalized(raw.profileRole, raw.profileRoleEn, locale),
    profileAvailability: pickLocalized(raw.profileAvailability, raw.profileAvailabilityEn, locale),
    profileAlso: pickLocalized(raw.profileAlso, raw.profileAlsoEn, locale),
    profileLocation: pickLocalized(raw.profileLocation, raw.profileLocationEn, locale),
    profileWorkMode: pickLocalized(raw.profileWorkMode, raw.profileWorkModeEn, locale),
    profileLanguages: pickLocalized(raw.profileLanguages, raw.profileLanguagesEn, locale),
    bioTitle: pickLocalized(raw.bioTitle, raw.bioTitleEn, locale),
    bioP1: pickLocalized(raw.bioP1, raw.bioP1En, locale),
    bioP2: pickLocalized(raw.bioP2, raw.bioP2En, locale),
    bioP3: pickLocalized(raw.bioP3, raw.bioP3En, locale),
    visionTitle: pickLocalized(raw.visionTitle, raw.visionTitleEn, locale),
    visionLede: pickLocalized(raw.visionLede, raw.visionLedeEn, locale),
    visionTodayText: pickLocalized(raw.visionTodayText, raw.visionTodayTextEn, locale),
    visionTomorrowText: pickLocalized(raw.visionTomorrowText, raw.visionTomorrowTextEn, locale),
    why1Title: pickLocalized(raw.why1Title, raw.why1TitleEn, locale),
    why1Desc: pickLocalized(raw.why1Desc, raw.why1DescEn, locale),
    why2Title: pickLocalized(raw.why2Title, raw.why2TitleEn, locale),
    why2Desc: pickLocalized(raw.why2Desc, raw.why2DescEn, locale),
    why3Title: pickLocalized(raw.why3Title, raw.why3TitleEn, locale),
    why3Desc: pickLocalized(raw.why3Desc, raw.why3DescEn, locale),
    why4Title: pickLocalized(raw.why4Title, raw.why4TitleEn, locale),
    why4Desc: pickLocalized(raw.why4Desc, raw.why4DescEn, locale),
    beyondLanguages: pickLocalizedList(raw.beyondLanguages, raw.beyondLanguagesEn, locale),
    beyondInterests: pickLocalizedList(raw.beyondInterests, raw.beyondInterestsEn, locale),
    ctaTitle: pickLocalized(raw.ctaTitle, raw.ctaTitleEn, locale),
    ctaSub: pickLocalized(raw.ctaSub, raw.ctaSubEn, locale),
  };
}
