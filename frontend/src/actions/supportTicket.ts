"use server";

import { apiFetchDynamic, apiPost, type ApiPostResult } from "@/lib/api/client";
import { apiPath, supportTicketTokenSchema } from "@/lib/api/path";
import { rateLimit } from "@/lib/rate-limit";
import { supportTicketSchema, supportTicketReplySchema, invalidInput } from "@/lib/validation/server";
import type { SupportTicketPayload, SupportTicketThread } from "@/lib/types";

export async function submitSupportTicket(payload: SupportTicketPayload): Promise<ApiPostResult> {
  if (!(await rateLimit("support-ticket", 5))) return { ok: false, error: "rate_limited" };

  const parsed = supportTicketSchema.safeParse(payload);
  if (!parsed.success) return invalidInput;

  // `parsed.data` et non `payload` : seuls les champs validés (et rognés)
  // atteignent le backend, jamais une clé supplémentaire glissée par l'appelant.
  return apiPost("/support_tickets", parsed.data);
}

/**
 * Le contrôleur "plain" (App\Controller\Api\SupportTicketPublicController) ne
 * renvoie pas de forme Hydra/JSON-LD — apiFetchDynamic() n'exige pas cette
 * forme pour parser la réponse. Jamais de cache (fil qui doit rester à jour),
 * et IP du visiteur transmise (limiteur backend par IP).
 *
 * Le jeton est validé AVANT d'être inséré dans le chemin (64 hex, même
 * contrainte que la route backend) : un jeton `../xxx` viserait sinon un
 * autre endpoint de l'API interne.
 */
export async function viewSupportTicket(token: string): Promise<SupportTicketThread | null> {
  const parsed = supportTicketTokenSchema.safeParse(token);
  if (!parsed.success) return null;

  return apiFetchDynamic<SupportTicketThread>(apiPath`/support_tickets/${parsed.data}`);
}

export async function replySupportTicket(token: string, message: string): Promise<ApiPostResult> {
  if (!(await rateLimit("support-ticket-reply", 10))) return { ok: false, error: "rate_limited" };

  const parsedToken = supportTicketTokenSchema.safeParse(token);
  const parsed = supportTicketReplySchema.safeParse({ message });
  if (!parsedToken.success || !parsed.success) return invalidInput;

  return apiPost(apiPath`/support_tickets/${parsedToken.data}/reply`, parsed.data);
}
