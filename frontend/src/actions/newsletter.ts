"use server";

import { apiPost, type ApiPostResult } from "@/lib/api/client";
import { rateLimit } from "@/lib/rate-limit";
import { newsletterSubscribeSchema, invalidInput } from "@/lib/validation/server";

export interface NewsletterSubscribePayload {
  email: string;
  locale: string;
}

/**
 * cf. App\ApiResource\NewsletterSubscriberApiResource — jusqu'ici
 * NewsletterForm.tsx était un stub purement visuel (faux succès local,
 * aucune persistance). Même structure que submitContactMessage
 * (actions/contact.ts) : rate-limit local, validation serveur (une Server
 * Action est un endpoint public, contournable par un appel direct — jamais
 * se fier uniquement à la validation react-hook-form du navigateur), puis
 * apiPost. Un e-mail déjà inscrit n'est PAS une erreur côté backend
 * (NewsletterSubscriberCreateProcessor le traite en ré-confirmation
 * idempotente) — ok:true dans les deux cas.
 */
export async function subscribeToNewsletter(
  payload: NewsletterSubscribePayload,
): Promise<ApiPostResult> {
  if (!(await rateLimit("newsletter-subscribe", 5))) return { ok: false, error: "rate_limited" };

  const parsed = newsletterSubscribeSchema.safeParse(payload);
  if (!parsed.success) return invalidInput;

  return apiPost("/newsletter_subscribers", parsed.data);
}
