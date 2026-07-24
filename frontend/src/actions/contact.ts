"use server";

import { apiPost, type ApiPostResult } from "@/lib/api/client";
import type {
  AppointmentAnswers,
  ContactMessagePayload,
  FreelanceApplicationAnswers,
} from "@/lib/types";

export async function submitContactMessage(
  payload: ContactMessagePayload,
): Promise<ApiPostResult> {
  return apiPost("/contact_messages", payload);
}

/**
 * Pas d'entité RDV/Appointment côté backend (cf. plan) : on réutilise
 * ContactMessage, le seul endpoint public générique, avec un sujet dédié pour
 * pouvoir le retrouver côté admin.
 */
export async function submitAppointmentRequest(
  answers: AppointmentAnswers,
): Promise<ApiPostResult> {
  const message = [
    `Créneau souhaité : ${answers.slot || "—"}`,
    `Sujet : ${answers.subject || "—"}`,
    `Email : ${answers.email || "—"}`,
    "---",
    answers.message || "(aucun message complémentaire)",
  ].join("\n");

  return submitContactMessage({
    name: answers.name,
    subject: "Demande de rendez-vous",
    message,
  });
}

/**
 * Pas d'entité Freelance côté backend (cf. plan) : idem, réutilise
 * ContactMessage avec un sujet dédié.
 */
export async function submitFreelanceApplication(
  answers: FreelanceApplicationAnswers,
): Promise<ApiPostResult> {
  const message = [
    `Spécialités : ${answers.specialties.join(", ") || "—"}`,
    `Disponibilité : ${answers.availability || "—"}`,
    `Email : ${answers.email || "—"}`,
    `Portfolio/lien : ${answers.link || "—"}`,
    "---",
    answers.message || "(aucun message complémentaire)",
  ].join("\n");

  return submitContactMessage({
    name: answers.name,
    subject: "Candidature freelance",
    message,
  });
}
