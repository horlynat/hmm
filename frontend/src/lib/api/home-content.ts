import { apiFetch, extractCollection, pickLocalized, pickLocalizedList } from "./client";
import type { HomeContent } from "@/lib/types";

// Contenu curé manuellement, mis à jour rarement — la fraîcheur réelle vient
// du webhook /api/revalidate (revalidateTag) déclenché par le backend ; ce
// délai n'est qu'un filet de sécurité si ce webhook venait à échouer.
const HOME_CONTENT_REVALIDATE_SECONDS = 60 * 60 * 24;

interface RawHomeContent extends HomeContent {
  heroEyebrowEn?: string | null;
  heroTitleEn?: string | null;
  heroTitleAccentEn?: string | null;
  heroSubEn?: string | null;
  heroRolesEn?: string[] | null;
  founderBadgeEn?: string | null;
  diagramCaptionEn?: string | null;
  aboutTitleEn?: string | null;
  aboutP1En?: string | null;
  aboutP2En?: string | null;
  aboutHighlightTitleEn?: string | null;
  aboutHighlightDescEn?: string | null;
  aboutVisionTextEn?: string | null;
  aboutMissionTextEn?: string | null;
  freelanceTitleEn?: string | null;
  freelanceLedeEn?: string | null;
  freelancePoint1En?: string | null;
  freelancePoint2En?: string | null;
  freelancePoint3En?: string | null;
  freelanceCardDescEn?: string | null;
  contactCtaTitleEn?: string | null;
  contactCtaSubEn?: string | null;
}

/** `locale` explicite — cf. commentaire dans projects.ts (bug de mémoïsation de `getLocale()`). */
export async function getHomeContent(locale: string): Promise<HomeContent | null> {
  const payload = await apiFetch<unknown>("/home_contents", {
    tags: ["home-content"],
    revalidate: HOME_CONTENT_REVALIDATE_SECONDS,
  });
  const raw = extractCollection<RawHomeContent>(payload)[0];
  if (!raw) return null;

  return {
    heroEyebrow: pickLocalized(raw.heroEyebrow, raw.heroEyebrowEn, locale),
    heroTitle: pickLocalized(raw.heroTitle, raw.heroTitleEn, locale),
    heroTitleAccent: pickLocalized(raw.heroTitleAccent, raw.heroTitleAccentEn, locale),
    heroSub: pickLocalized(raw.heroSub, raw.heroSubEn, locale),
    heroRoles: pickLocalizedList(raw.heroRoles, raw.heroRolesEn, locale),
    founderBadge: pickLocalized(raw.founderBadge, raw.founderBadgeEn, locale),
    diagramCaption: pickLocalized(raw.diagramCaption, raw.diagramCaptionEn, locale),
    aboutTitle: pickLocalized(raw.aboutTitle, raw.aboutTitleEn, locale),
    aboutP1: pickLocalized(raw.aboutP1, raw.aboutP1En, locale),
    aboutP2: pickLocalized(raw.aboutP2, raw.aboutP2En, locale),
    aboutHighlightTitle: pickLocalized(raw.aboutHighlightTitle, raw.aboutHighlightTitleEn, locale),
    aboutHighlightDesc: pickLocalized(raw.aboutHighlightDesc, raw.aboutHighlightDescEn, locale),
    aboutVisionText: pickLocalized(raw.aboutVisionText, raw.aboutVisionTextEn, locale),
    aboutMissionText: pickLocalized(raw.aboutMissionText, raw.aboutMissionTextEn, locale),
    freelanceTitle: pickLocalized(raw.freelanceTitle, raw.freelanceTitleEn, locale),
    freelanceLede: pickLocalized(raw.freelanceLede, raw.freelanceLedeEn, locale),
    freelancePoint1: pickLocalized(raw.freelancePoint1, raw.freelancePoint1En, locale),
    freelancePoint2: pickLocalized(raw.freelancePoint2, raw.freelancePoint2En, locale),
    freelancePoint3: pickLocalized(raw.freelancePoint3, raw.freelancePoint3En, locale),
    freelanceCardDesc: pickLocalized(raw.freelanceCardDesc, raw.freelanceCardDescEn, locale),
    contactCtaTitle: pickLocalized(raw.contactCtaTitle, raw.contactCtaTitleEn, locale),
    contactCtaSub: pickLocalized(raw.contactCtaSub, raw.contactCtaSubEn, locale),
  };
}
