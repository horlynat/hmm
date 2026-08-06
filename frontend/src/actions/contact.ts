"use server";

import { apiPost, type ApiPostResult } from "@/lib/api/client";
import { rateLimit } from "@/lib/rate-limit";
import { contactMessageSchema, invalidInput } from "@/lib/validation/server";
import type { AppointmentAnswers, ContactMessagePayload } from "@/lib/types";

export async function submitContactMessage(
  payload: ContactMessagePayload,
): Promise<ApiPostResult> {
  if (!(await rateLimit("contact-message", 8))) return { ok: false, error: "rate_limited" };

  const parsed = contactMessageSchema.safeParse(payload);
  if (!parsed.success) return invalidInput;

  return apiPost("/contact_messages", payload);
}

/**
 * Pas d'entité RDV/Appointment dédiée côté backend : on réutilise
 * ContactMessage, avec `source: "Rendez-vous"` pour le distinguer côté admin.
 * Chaque réponse a désormais son propre champ structuré (company, phone,
 * channel, slot) au lieu d'être noyée dans le texte libre.
 */
export async function submitAppointmentRequest(
  answers: AppointmentAnswers,
): Promise<ApiPostResult> {
  return submitContactMessage({
    source: "Rendez-vous",
    name: answers.name,
    company: answers.company || undefined,
    email: answers.email,
    phone: answers.phone || undefined,
    channel: answers.canal,
    slot: answers.slot,
    subject: answers.subject,
    message: answers.message || "(aucun message complémentaire)",
  });
}
