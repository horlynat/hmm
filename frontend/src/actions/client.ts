"use server";

import { apiPost, type ApiPostResult } from "@/lib/api/client";
import { rateLimit } from "@/lib/rate-limit";
import { clientAnswersSchema, invalidInput } from "@/lib/validation/server";
import type {
  ClientRegistrationAnswers,
  ClientRegistrationPayload,
} from "@/lib/types";

/**
 * Inscription publique "client" — cf. App\ApiResource\ClientRegistrationApiResource.
 * Crée un vrai compte client (ROLE_USER) ; le compte se distingue d'un
 * collaborateur non par un rôle mais par ses attributions (projets/devis).
 */
export async function registerClient(
  answers: ClientRegistrationAnswers,
): Promise<ApiPostResult> {
  if (!(await rateLimit("register-client"))) return { ok: false, error: "rate_limited" };

  const parsed = clientAnswersSchema.safeParse(answers);
  if (!parsed.success) return invalidInput;

  const payload: ClientRegistrationPayload = {
    email: parsed.data.email,
    fullName: parsed.data.name,
    phone: parsed.data.phone || undefined,
    plainPassword: parsed.data.password,
    agreeTerms: parsed.data.agreeTerms,
  };

  return apiPost("/client_registrations", payload);
}
