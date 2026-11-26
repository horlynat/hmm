import "server-only";
import { apiFetch, extractCollection, pickLocalized, pickLocalizedList } from "./client";
import type { SkillDomainContent } from "@/lib/types";
import { PROTO_ICONS } from "@/components/proto/assets";
import type { ProtoIconName } from "@/components/proto/assets";

const SKILL_DOMAINS_REVALIDATE_SECONDS = 60 * 60 * 24;

interface RawSkillDomain {
  id?: unknown;
  position?: unknown;
  icon?: unknown;
  title?: unknown;
  titleEn?: unknown;
  short?: unknown;
  shortEn?: unknown;
  text?: unknown;
  textEn?: unknown;
  doItems?: unknown;
  doItemsEn?: unknown;
  tools?: unknown;
  toolsEn?: unknown;
  offerSlugs?: unknown;
  proofProjectSlug?: unknown;
}

const str = (v: unknown): string => (typeof v === "string" ? v : "");
const strOrNull = (v: unknown): string | null => (typeof v === "string" && v !== "" ? v : null);
const list = (v: unknown): string[] => (Array.isArray(v) ? v.filter((x): x is string => typeof x === "string" && x.trim() !== "") : []);
const isIcon = (v: unknown): v is ProtoIconName => typeof v === "string" && Object.hasOwn(PROTO_ICONS, v);

/**
 * Domaines de compétence (onglets « Compétences » de la page À propos), dans
 * l'ordre du back-office. `null` si l'API ne répond pas — à distinguer d'une
 * liste vide (aucun domaine saisi) : l'appelant refuse alors de générer une
 * page amputée (cf. SiteAbout). Rien n'est supposé de la forme des données.
 */
export async function getSkillDomains(locale: string): Promise<SkillDomainContent[] | null> {
  const payload = await apiFetch<unknown>("/skill_domains", {
    tags: ["skill-domains"],
    revalidate: SKILL_DOMAINS_REVALIDATE_SECONDS,
  });
  if (payload === null) return null;

  return extractCollection<RawSkillDomain>(payload)
    .filter((d) => str(d.title).trim() !== "")
    .map((d) => ({
      icon: isIcon(d.icon) ? d.icon : "arch",
      title: pickLocalized(str(d.title), strOrNull(d.titleEn), locale),
      short: pickLocalized(str(d.short), strOrNull(d.shortEn), locale),
      text: pickLocalized(str(d.text), strOrNull(d.textEn), locale),
      do: pickLocalizedList(list(d.doItems), list(d.doItemsEn), locale),
      tools: pickLocalizedList(list(d.tools), list(d.toolsEn), locale),
      offerSlugs: list(d.offerSlugs),
      proofProjectSlug: strOrNull(d.proofProjectSlug),
    }));
}
