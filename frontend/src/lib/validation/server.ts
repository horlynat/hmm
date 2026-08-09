import "server-only";
import { z } from "zod";
import type { ApiPostResult } from "@/lib/api/client";

/**
 * Validation côté serveur des Server Actions. Une Server Action est un endpoint
 * HTTP public : un attaquant peut l'appeler directement en contournant la
 * validation react-hook-form du navigateur. Ces schémas rejettent donc toute
 * entrée malformée AVANT l'appel API — bornes de longueur incluses, pour éviter
 * qu'un payload démesuré atteigne le backend.
 */

const email = z.string().trim().min(1).max(180).regex(/^[^\s@]+@[^\s@]+\.[^\s@]+$/);
const strongPassword = z
  .string()
  .max(200)
  .regex(/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[\W_]).{8,}$/);
const optionalText = (max: number) => z.string().max(max).optional().or(z.literal(""));

export const clientAnswersSchema = z.object({
  name: z.string().trim().min(1).max(120),
  email,
  phone: optionalText(30),
  password: strongPassword,
  agreeTerms: z.literal(true),
});

export const collaboratorAnswersSchema = z.object({
  name: z.string().trim().min(1).max(120),
  email,
  phone: optionalText(30),
  password: strongPassword,
  agreeTerms: z.literal(true),
  specialties: z.array(z.string().max(60)).max(20).optional(),
  availability: optionalText(120),
  portfolioUrl: optionalText(300),
  bio: optionalText(2000),
});

export const quoteAnswersSchema = z.object({
  name: z.string().trim().min(1).max(120),
  email,
  phone: optionalText(30),
  type: z.string().min(1).max(120),
  categoryDetail: optionalText(500),
  source: optionalText(120),
  description: z.string().max(5000).optional().or(z.literal("")),
  fileName: optionalText(255),
  budget: optionalText(120),
  currency: optionalText(10),
  delai: optionalText(120),
  canal: z.string().min(1).max(60),
  clarifications: z
    .array(z.object({ question: z.string().max(500), answer: z.string().max(2000) }))
    .max(10),
});

export const contactMessageSchema = z.object({
  source: z.string().min(1).max(60),
  name: z.string().trim().min(1).max(120),
  email,
  subject: z.string().min(1).max(200),
  message: z.string().min(1).max(5000),
  company: z.string().max(120).optional(),
  phone: z.string().max(30).optional(),
  channel: z.string().max(60).optional(),
  slot: z.string().max(60).optional(),
});

export const supportTicketSchema = z.object({
  name: z.string().trim().min(1).max(255),
  email,
  subject: z.string().trim().min(1).max(255),
  message: z.string().min(10).max(5000),
});

export const supportTicketReplySchema = z.object({
  message: z.string().min(10).max(5000),
});

/** Résultat standard d'échec de validation serveur, compatible ApiPostResult. */
export const invalidInput: ApiPostResult = { ok: false, error: "invalid_input" };
