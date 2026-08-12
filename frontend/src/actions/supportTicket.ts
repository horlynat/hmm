"use server";

import { apiFetch, apiPost, type ApiPostResult } from "@/lib/api/client";
import { rateLimit } from "@/lib/rate-limit";
import { supportTicketSchema, supportTicketReplySchema, invalidInput } from "@/lib/validation/server";
import type { SupportTicketPayload, SupportTicketThread } from "@/lib/types";

export async function submitSupportTicket(payload: SupportTicketPayload): Promise<ApiPostResult> {
  if (!(await rateLimit("support-ticket", 5))) return { ok: false, error: "rate_limited" };

  const parsed = supportTicketSchema.safeParse(payload);
  if (!parsed.success) return invalidInput;

  return apiPost("/support_tickets", payload);
}

/**
 * Le contrôleur "plain" (App\Controller\Api\SupportTicketPublicController) ne
 * renvoie pas de forme Hydra/JSON-LD — apiFetch() n'exige pas cette forme
 * pour parser la réponse, il fonctionne donc tel quel. revalidate: 0 pour ne
 * jamais mettre en cache un fil qui doit rester à jour.
 */
export async function viewSupportTicket(token: string): Promise<SupportTicketThread | null> {
  return apiFetch<SupportTicketThread>(`/support_tickets/${token}`, { revalidate: 0 });
}

export async function replySupportTicket(token: string, message: string): Promise<ApiPostResult> {
  if (!(await rateLimit("support-ticket-reply", 10))) return { ok: false, error: "rate_limited" };

  const parsed = supportTicketReplySchema.safeParse({ message });
  if (!parsed.success) return invalidInput;

  return apiPost(`/support_tickets/${token}/reply`, { message });
}
