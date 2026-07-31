import { apiFetch, extractCollection, pickLocalized, pickLocalizedList } from "./client";
import type { AiAssistantEntry, AiAssistantSettings } from "@/lib/types";

// Contenu curé manuellement, mis à jour rarement — la fraîcheur réelle vient
// du webhook /api/revalidate (revalidateTag) déclenché par le backend ; ce
// délai n'est qu'un filet de sécurité si ce webhook venait à échouer.
const AI_ASSISTANT_REVALIDATE_SECONDS = 60 * 60 * 24;

interface RawAiAssistantSettings extends AiAssistantSettings {
  greetingEn?: string | null;
  fallbackEn?: string | null;
}

/** `locale` explicite — cf. commentaire dans projects.ts (bug de mémoïsation de `getLocale()`). */
export async function getAiAssistantSettings(locale: string): Promise<AiAssistantSettings | null> {
  const payload = await apiFetch<unknown>("/ai_assistant_settings", {
    tags: ["ai-assistant"],
    revalidate: AI_ASSISTANT_REVALIDATE_SECONDS,
  });
  const raw = extractCollection<RawAiAssistantSettings>(payload)[0];
  if (!raw) return null;

  return {
    greeting: pickLocalized(raw.greeting, raw.greetingEn, locale),
    fallback: pickLocalized(raw.fallback, raw.fallbackEn, locale),
  };
}

interface RawAiAssistantEntry extends AiAssistantEntry {
  chipLabelEn?: string | null;
  keywordsEn?: string[] | null;
  answerEn?: string | null;
}

export async function getAiAssistantEntries(locale: string): Promise<AiAssistantEntry[]> {
  const payload = await apiFetch<unknown>("/ai_assistant_entries", {
    tags: ["ai-assistant"],
    revalidate: AI_ASSISTANT_REVALIDATE_SECONDS,
  });
  return extractCollection<RawAiAssistantEntry>(payload)
    .map(({ chipLabelEn, keywordsEn, answerEn, ...entry }) => ({
      ...entry,
      chipLabel: pickLocalized(entry.chipLabel, chipLabelEn, locale),
      keywords: pickLocalizedList(entry.keywords, keywordsEn, locale),
      answer: pickLocalized(entry.answer, answerEn, locale),
    }))
    .sort((a, b) => a.sortOrder - b.sortOrder);
}
