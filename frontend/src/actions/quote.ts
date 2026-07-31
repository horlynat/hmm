"use server";

import { apiPost, type ApiPostResult } from "@/lib/api/client";
import { rateLimit } from "@/lib/rate-limit";
import { quoteAnswersSchema, invalidInput } from "@/lib/validation/server";
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
    name: answers.name,
    email: answers.email,
    phone: answers.phone || undefined,
    category: answers.type,
    categoryDetail: answers.categoryDetail || undefined,
    source: answers.source || undefined,
    budget: answers.budget ? `${answers.budget}` : undefined,
    currency: answers.currency || undefined,
    timeline: answers.delai || undefined,
    channel: answers.canal,
    attachmentName: answers.fileName || undefined,
    clarifications: answers.clarifications.length > 0 ? answers.clarifications : undefined,
    message: answers.description || "(aucune description fournie)",
  };

  return apiPost("/quote_requests", payload);
}
