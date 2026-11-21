"use server";

import { apiPost, type ApiPostResult } from "@/lib/api/client";
import { rateLimit } from "@/lib/rate-limit";
import { collaboratorAnswersSchema, invalidInput } from "@/lib/validation/server";
import type {
  CollaboratorRegistrationAnswers,
  CollaboratorRegistrationPayload,
} from "@/lib/types";

/**
 * Un freelance est un compte réel (rôles USER/EDITOR/MODERATOR selon
 * promotion), pas un simple message — cf. App\ApiResource\CollaboratorRegistrationApiResource.
 * Le rôle ROLE_EDITOR (collaborateur) est attribué ensuite par un
 * administrateur depuis /admin/collaborators/candidates.
 */
export async function registerCollaborator(
  answers: CollaboratorRegistrationAnswers,
): Promise<ApiPostResult> {
  if (!(await rateLimit("register-collaborator"))) return { ok: false, error: "rate_limited" };

  const parsed = collaboratorAnswersSchema.safeParse(answers);
  if (!parsed.success) return invalidInput;

  const payload: CollaboratorRegistrationPayload = {
    email: parsed.data.email,
    fullName: parsed.data.name,
    plainPassword: parsed.data.password,
    agreeTerms: parsed.data.agreeTerms,
    specialties: parsed.data.specialties && parsed.data.specialties.length > 0 ? parsed.data.specialties : undefined,
    availability: parsed.data.availability || undefined,
    portfolioUrl: parsed.data.portfolioUrl || undefined,
    bio: parsed.data.bio || undefined,
  };

  return apiPost("/collaborator_registrations", payload);
}
