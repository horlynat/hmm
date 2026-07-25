/**
 * Types miroir du groupe de sérialisation `api_public` du backend (branche
 * `backend`/`main`, App\ApiResource\*). Volontairement limités aux champs
 * publics — pas de champs `api_admin`.
 */

export type ProjectStatus =
  | "a_venir"
  | "en_cours"
  | "suspendu"
  | "collaboration"
  | "termine";

export type ProjectPriority = "low" | "medium" | "high" | "critical";

export type BillingType = "fixed" | "time_and_materials" | "retainer";

export interface Tag {
  id: number;
  name: string;
}

export interface Media {
  id: number;
  filePath: string;
  altText: string | null;
  mimeType: string | null;
  size: number | null;
  uploadedAt: string | null;
  type: "image" | "video" | "audio" | "document" | null;
}

export interface Project {
  id: number;
  slug: string;
  title: string;
  description: string;
  link: string;
  status: ProjectStatus;
  priority: ProjectPriority | null;
  billingType: BillingType | null;
  progress: number;
}

export interface Article {
  id: number;
  slug: string;
  title: string;
  content: string;
  tags: Tag[];
  media: Media[];
}

export interface SkillCategory {
  id: number;
  name: string;
}

export interface Skill {
  id: number;
  name: string;
  level: number;
}

export interface Experience {
  id: number;
  company: string;
  role: string;
  description: string;
}

export interface Course {
  id: number;
  title: string;
  institution: string;
  description: string;
}

export interface Testimonial {
  id: number;
  author: string;
  content: string;
  rating: string | null;
  media: Media[];
}

/** Payload envoyé par le frontend — le backend n'accepte actuellement que ces champs (cf. plan). */
export interface ContactMessagePayload {
  name: string;
  subject: string;
  message: string;
}

/** Payload envoyé par le frontend — le backend n'accepte actuellement que ces champs (cf. plan). */
export interface QuoteRequestPayload {
  name: string;
  message: string;
}

/**
 * Réponses collectées par le wizard "devis" du formulaire de contact.
 * Les champs sans équivalent côté backend (budget, délai, canal, email,
 * précisions IA...) sont empaquetés dans le `message` de QuoteRequestPayload
 * par actions/quote.ts — voir le plan pour la justification.
 */
export interface QuoteWizardAnswers {
  type: string;
  source: string;
  description: string;
  fileName: string;
  budget: string;
  currency: string;
  delai: string;
  name: string;
  email: string;
  canal: string;
  clarifications: string[];
}

/** Réponses du mode "rendez-vous" du formulaire de contact — envoyées via ContactMessage. */
export interface AppointmentAnswers {
  name: string;
  email: string;
  slot: string;
  subject: string;
  message: string;
}

/** Réponses du formulaire de candidature freelance — envoyées via ContactMessage. */
export interface FreelanceApplicationAnswers {
  name: string;
  email: string;
  specialties: string[];
  availability: string;
  link: string;
  message: string;
}
