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
    email: answers.email,
    fullName: answers.name,
    plainPassword: answers.password,
    agreeTerms: answers.agreeTerms,
    specialties: answers.specialties.length > 0 ? answers.specialties : undefined,
    availability: answers.availability || undefined,
    portfolioUrl: answers.portfolioUrl || undefined,
    bio: answers.bio || undefined,
  };

  return apiPost("/collaborator_registrations", payload);
}
