"use server";

import { z } from "zod";
import { apiPost, apiPostWithData, type ApiPostResult } from "@/lib/api/client";
import { getToken } from "@/lib/auth/session";
import { rateLimit } from "@/lib/rate-limit";
import { quoteAnswersSchema, quoteQualifyAnswersSchema, invalidInput } from "@/lib/validation/server";
import type { QuoteRequestPayload, QuoteWizardAnswers } from "@/lib/types";

/**
 * Miroir 1:1 du schéma structuré de App\Entity\QuoteRequest (groupe
 * `api_public`) — chaque réponse du wizard alimente son propre champ, plus
 * de fourre-tout texte. `message` ne contient que la description libre du
 * client, lisible sans reconstruction côté admin.
 */
export async function submitQuoteRequest(
  answers: QuoteWizardAnswers,
): Promise<ApiPostResult> {
  if (!(await rateLimit("quote-request"))) return { ok: false, error: "rate_limited" };

  const parsed = quoteAnswersSchema.safeParse(answers);
  if (!parsed.success) return invalidInput;

  const payload: QuoteRequestPayload = {
    name: parsed.data.name,
    email: parsed.data.email,
    phone: parsed.data.phone || undefined,
    category: parsed.data.type,
    categoryDetail: parsed.data.categoryDetail || undefined,
    source: parsed.data.source || undefined,
    budget: parsed.data.budget ? `${parsed.data.budget}` : undefined,
    currency: parsed.data.currency || undefined,
    timeline: parsed.data.delai || undefined,
    channel: parsed.data.canal,
    attachmentName: parsed.data.fileName || undefined,
    clarifications: parsed.data.clarifications.length > 0 ? parsed.data.clarifications : undefined,
    message: parsed.data.description || "(aucune description fournie)",
  };

  // Si l'auteur est connecté au moment de l'envoi, on rattache la demande à
  // son compte (le backend l'associe via le Bearer token — cf.
  // App\State\QuoteRequestCreateProcessor) pour qu'elle apparaisse dans « Mes
  // devis » sans intervention manuelle d'un admin. Reste anonyme sinon.
  const token = await getToken();

  return apiPost("/quote_requests", payload, token ? { token } : {});
}

export type QualifyQuoteResult = { ok: true; questions: string[] } | { ok: false; error: string };

/**
 * Génère 1 à 2 questions de qualification dynamiques (Claude Sonnet, cf.
 * App\State\QuoteQualifyProcessor) à partir des réponses projet déjà saisies
 * dans le wizard — appelé juste avant la transition vers l'étape "ia-qualif"
 * (cf. useQuoteWizard.ts::fetchIaQuestions). N'envoie jamais name/email/phone/
 * canal : sans rapport avec la qualification du projet. Ne throw jamais —
 * l'appelant retombe sur ses questions codées en dur sur tout `ok:false` ou
 * tableau vide, sans avoir à distinguer la cause de l'échec.
 */
export async function qualifyQuoteRequest(
  answers: Pick<
    QuoteWizardAnswers,
    "type" | "categoryDetail" | "source" | "description" | "budget" | "currency" | "delai"
  >,
  locale: string,
): Promise<QualifyQuoteResult> {
  if (!(await rateLimit("quote-qualify", 20, 60 * 60 * 1000))) {
    return { ok: false, error: "rate_limited" };
  }

  const parsed = quoteQualifyAnswersSchema.safeParse(answers);
  // Locale bornée à la liste fermée du site : une valeur libre atteindrait
  // le backend (colonne de 5 caractères du journal de coût IA).
  const parsedLocale = z.enum(["fr", "en"]).safeParse(locale);
  if (!parsed.success || !parsedLocale.success) return { ok: false, error: "invalid_input" };

  const result = await apiPostWithData<{ questions: string[] }>(
    "/quote/qualify",
    { ...parsed.data, locale: parsedLocale.data },
    // Couvre le timeout de 30s côté App\Service\ClaudeClient + marge — même
    // arbitrage que la route proxy de l'assistant IA (src/app/api/ai-assistant/chat/route.ts).
    { timeoutMs: 35_000 },
  );
  if (!result.ok) return result;

  return { ok: true, questions: Array.isArray(result.data.questions) ? result.data.questions : [] };
}
