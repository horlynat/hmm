"use server";

import { apiPost, type ApiPostResult } from "@/lib/api/client";
import type { QuoteWizardAnswers } from "@/lib/types";

/**
 * Le groupe `api_public` de QuoteRequest n'accepte que `{ name, message }`
 * (cf. plan — email/téléphone requis par l'entité mais absents du groupe
 * public, `user` requis sans valeur anonyme). On empaquette donc tout le
 * reste du wizard dans `message`, rien n'est perdu, juste non structuré côté
 * backend pour l'instant.
 */
function formatQuoteMessage(answers: QuoteWizardAnswers): string {
  const lines = [
    `Prestation : ${answers.type || "—"}`,
    `Trouvé via : ${answers.source || "—"}`,
    `Budget : ${answers.budget ? `${answers.budget} ${answers.currency}` : "—"}`,
    `Délai : ${answers.delai || "—"}`,
    `Email : ${answers.email || "—"}`,
    `Canal préféré : ${answers.canal || "—"}`,
    answers.fileName ? `Pièce jointe mentionnée : ${answers.fileName}` : null,
    answers.clarifications.length > 0
      ? `Précisions (assistant) : ${answers.clarifications.join(" / ")}`
      : null,
    "---",
    answers.description || "(aucune description fournie)",
  ].filter((line): line is string => Boolean(line));

  return lines.join("\n");
}

export async function submitQuoteRequest(
  answers: QuoteWizardAnswers,
): Promise<ApiPostResult> {
  return apiPost("/quote_requests", {
    name: answers.name,
    message: formatQuoteMessage(answers),
  });
}
