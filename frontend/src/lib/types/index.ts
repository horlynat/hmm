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

/**
 * Contenu "vitrine" d'un projet — rôle, stack, défis/solutions, résultats,
 * preuves visuelles. Miroir de App\Entity\ProjectInfo (relation 1-1 avec
 * Project côté backend), qui garde ce contenu éditorial public à part des
 * champs de gestion de Project (budget, deadline, équipe... en `api_admin`).
 */
export interface ProjectInfo {
  role: string | null;
  objectives: string[];
  techStack: { name: string; rationale: string | null }[];
  challenges: { problem: string; solution: string }[];
  results: { label: string; value: string }[];
  repoUrl: string | null;
  coverImage: Media | null;
  architectureDiagram: Media | null;
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
  media: Media[];
  info: ProjectInfo | null;
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

/**
 * Contenu narratif de la page d'accueil — miroir de App\Entity\HomeContent
 * (ligne unique en base, éditable en back-office). Déjà résolu dans la
 * langue courante côté API layer (cf. src/lib/api/home-content.ts) : pas de
 * champs `*En` ici.
 */
export interface HomeContent {
  heroEyebrow: string;
  heroTitle: string;
  heroTitleAccent: string;
  heroSub: string;
  heroRoles: string[];
  founderBadge: string;
  diagramCaption: string;
  aboutTitle: string;
  aboutP1: string;
  aboutP2: string;
  aboutHighlightTitle: string;
  aboutHighlightDesc: string;
  aboutVisionText: string;
  aboutMissionText: string;
  freelanceTitle: string;
  freelanceLede: string;
  freelancePoint1: string;
  freelancePoint2: string;
  freelancePoint3: string;
  freelanceCardDesc: string;
  contactCtaTitle: string;
  contactCtaSub: string;
}

/**
 * Contenu narratif de la page "À propos" — miroir de App\Entity\AboutContent
 * (ligne unique en base, éditable en back-office). Déjà résolu dans la
 * langue courante côté API layer (cf. src/lib/api/about-content.ts).
 */
export interface AboutContent {
  heroEyebrow: string;
  heroTitle: string;
  heroTitleAccent: string;
  heroSub: string;
  profileName: string;
  profileRole: string;
  profileAvailability: string;
  profileAlso: string;
  profileLocation: string;
  profileWorkMode: string;
  profileLanguages: string;
  bioTitle: string;
  bioP1: string;
  bioP2: string;
  bioP3: string;
  visionTitle: string;
  visionLede: string;
  visionTodayText: string;
  visionTomorrowText: string;
  why1Title: string;
  why1Desc: string;
  why2Title: string;
  why2Desc: string;
  why3Title: string;
  why3Desc: string;
  why4Title: string;
  why4Desc: string;
  beyondLanguages: string[];
  beyondInterests: string[];
  ctaTitle: string;
  ctaSub: string;
}

/** Réglages du widget assistant IA — miroir de App\Entity\AiAssistantSettings. */
export interface AiAssistantSettings {
  greeting: string;
  fallback: string;
}

/** Entrée de FAQ du widget assistant IA — miroir de App\Entity\AiAssistantEntry. */
export interface AiAssistantEntry {
  id: number;
  chipLabel: string;
  keywords: string[];
  answer: string;
  sortOrder: number;
}

/** Miroir du groupe `api_public` de App\Entity\ContactMessage. */
export interface ContactMessagePayload {
  source: string;
  name: string;
  company?: string;
  email: string;
  phone?: string;
  channel?: string;
  slot?: string;
  subject: string;
  message: string;
}

/** Miroir du groupe `api_public` de App\Entity\QuoteRequest — schéma structuré, un champ backend = un champ payload. */
export interface QuoteRequestPayload {
  name: string;
  email: string;
  phone?: string;
  category: string;
  categoryDetail?: string;
  source?: string;
  budget?: string;
  currency?: string;
  timeline?: string;
  channel: string;
  attachmentName?: string;
  clarifications?: { question: string; answer: string }[];
  message: string;
}

/**
 * Réponses collectées par le wizard "devis" du formulaire de contact.
 * `categoryDetail` est la réponse à la question de qualification spécifique
 * au métier choisi (étape 2) — cf. CATEGORY_QUESTIONS dans QuoteWizard.tsx.
 */
export interface QuoteWizardAnswers {
  type: string;
  categoryDetail: string;
  source: string;
  description: string;
  fileName: string;
  budget: string;
  currency: string;
  delai: string;
  name: string;
  email: string;
  phone: string;
  canal: string;
  clarifications: { question: string; answer: string }[];
}

/** Réponses du mode "rendez-vous" du formulaire de contact — envoyées via ContactMessage. */
export interface AppointmentAnswers {
  name: string;
  company: string;
  email: string;
  phone: string;
  canal: string;
  slot: string;
  subject: string;
  message: string;
}

/**
 * Miroir du groupe `collaborator_signup` de App\Entity\User — un freelance
 * est un vrai compte (rôles USER/EDITOR/MODERATOR selon promotion), pas un
 * simple message. `agreeTerms` n'est jamais persisté (comme sur le formulaire
 * web /register), juste validé côté serveur.
 */
export interface CollaboratorRegistrationPayload {
  email: string;
  fullName: string;
  phone?: string;
  plainPassword: string;
  agreeTerms: boolean;
  specialties?: string[];
  availability?: string;
  portfolioUrl?: string;
  bio?: string;
}

/** Réponses collectées par le formulaire d'inscription freelance (espace collaborateurs). */
export interface CollaboratorRegistrationAnswers {
  name: string;
  email: string;
  password: string;
  agreeTerms: boolean;
  specialties: string[];
  availability: string;
  portfolioUrl: string;
  bio: string;
}

/**
 * Miroir du groupe `client_signup` de App\ApiResource\ClientRegistrationApiResource.
 * Un client est un vrai compte (ROLE_USER) créé depuis le frontend ; `agreeTerms`
 * n'est jamais persisté, seulement validé côté serveur.
 */
export interface ClientRegistrationPayload {
  email: string;
  fullName: string;
  phone?: string;
  plainPassword: string;
  agreeTerms: boolean;
}

/** Réponses collectées par le formulaire d'inscription client. */
export interface ClientRegistrationAnswers {
  name: string;
  email: string;
  phone: string;
  password: string;
  agreeTerms: boolean;
}

/** Projet tel que renvoyé par GET /api/me (App\Controller\Api\MeController). */
export interface SessionProject {
  id: number;
  title: string;
  status: string;
  statusLabel: string;
  progress: number;
  deadline: string | null;
}

/** Devis tel que renvoyé par GET /api/me. */
export interface SessionQuote {
  id: number;
  category: string;
  status: string;
  budget: string | null;
  currency: string | null;
}

/** Attributions de l'utilisateur courant selon son rôle (GET /api/me). */
export interface SessionAttributions {
  collaboratingProjects: SessionProject[];
  ownedProjects: SessionProject[];
  clientProjects: SessionProject[];
  quoteRequests: SessionQuote[];
}

/**
 * Utilisateur courant (self-service) — miroir du payload de
 * App\Controller\Api\MeController::serializeUser(). Jamais de champ sensible
 * (aucun rôle n'est modifiable ici — cf. `editableFields`).
 */
export interface SessionUser {
  id: number;
  email: string;
  fullName: string | null;
  phone: string | null;
  bio: string | null;
  profileImage: string | null;
  specialties: string[] | null;
  availability: string | null;
  portfolioUrl: string | null;
  roles: string[];
  isVerified: boolean;
  isTwoFactorEnabled: boolean;
  isCollaborator: boolean;
  editableFields: string[];
  attributions: SessionAttributions;
}

/** Champs auto-modifiables via PATCH /api/me. */
export interface ProfileUpdatePayload {
  fullName?: string;
  phone?: string;
  bio?: string;
  specialties?: string[];
  availability?: string;
  portfolioUrl?: string;
  plainPassword?: string;
}
