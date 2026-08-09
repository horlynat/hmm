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

/** Payload envoyé à POST /api/ai-assistant/chat (proxy vers /api/assistant/chat). */
export interface AiAssistantChatPayload {
  question: string;
  history: Array<{ role: "user" | "assistant"; text: string }>;
  locale: string;
}

export type AiAssistantChatResult =
  | { ok: true; answer: string; suggestions: string[] }
  | { ok: false; error: "rate_limited" | "unavailable" | "network_error" };

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
  /** Peut être `null` sur d'anciens projets jamais retouchés depuis l'ajout du champ. */
  updatedAt: string | null;
}

/** Devis tel que renvoyé par GET /api/me. */
export interface SessionQuote {
  id: number;
  category: string;
  status: string;
  budget: string | null;
  currency: string | null;
  /** Peut être `null` pour les demandes créées avant l'ajout de ce champ — cf. QuoteRequest::$createdAt. */
  createdAt: string | null;
  /** Renseignés une fois le devis converti en projet suivi (cf. AdminQuoteRequestController::convert()). */
  convertedProjectId: number | null;
  convertedProjectTitle: string | null;
}

export type InvoiceStatus = "pending" | "revision_requested" | "paid" | "cancelled";

/**
 * Facture émise au client pour un projet — miroir de GET /api/me
 * (App\Entity\Invoice). Uniquement pour les projets dont l'utilisateur est
 * le client : jamais renvoyée à l'équipe (owner/collaborateur), même
 * restriction de périmètre que budget/spent sur Project.
 */
export interface SessionInvoice {
  id: number;
  projectId: number;
  projectTitle: string;
  number: string;
  label: string;
  amount: string;
  currency: string;
  formattedAmount: string;
  status: InvoiceStatus;
  statusLabel: string;
  issuedAt: string;
  dueDate: string | null;
  paidAt: string | null;
  validatedAt: string | null;
  overdue: boolean;
}

/**
 * Entrée d'historique de projet (ProjectHistory) — miroir de GET
 * /api/me/activity. Le client ne voit qu'un sous-ensemble « sûr » de
 * l'historique (cycle de vie du projet), l'équipe voit tout sauf les refus
 * d'accès — cf. MeController::CLIENT_SAFE_HISTORY_ACTIONS.
 */
export interface SessionActivityEntry {
  id: number;
  projectId: number;
  projectTitle: string;
  action: string;
  actionLabel: string;
  details: string | null;
  createdAt: string;
}

/** Message posté dans le fil de discussion d'un projet (Comment) — miroir de GET /api/me/activity et /api/me/projects/{id}/comments. */
export interface SessionComment {
  id: number;
  projectId: number;
  projectTitle: string;
  content: string;
  createdAt: string;
  isMine: boolean;
  author: {
    id: number;
    fullName: string | null;
    email: string;
  };
}

/** Miroir de GET /api/me/activity. */
export interface SessionActivity {
  history: SessionActivityEntry[];
  messages: SessionComment[];
}

/**
 * Détail complet d'un projet auquel l'utilisateur courant est rattaché —
 * miroir de GET /api/me/projects/{id} (App\Controller\Api\MeController).
 * `statusLabel`/`priorityLabel`/`billingTypeLabel` sont déjà résolus en
 * français côté backend (pas d'i18n sur ces enums pour l'instant — même
 * limitation déjà acceptée sur `SessionProject.statusLabel`).
 *
 * Volontairement sans `budget`/`spent` : ce sont des chiffres de gestion
 * interne (`#[Groups(['api_admin'])]` côté entité), pas ce que le client a
 * payé — ni le client ni le collaborateur ne doivent les voir ici.
 */
export interface SessionProjectDetail {
  id: number;
  slug: string;
  title: string;
  description: string;
  link: string;
  status: ProjectStatus;
  statusLabel: string;
  priority: ProjectPriority | null;
  priorityLabel: string | null;
  billingType: BillingType | null;
  billingTypeLabel: string | null;
  progress: number;
  deadline: string | null;
  skills: { id: number; name: string }[];
  tags: Tag[];
  media: Media[];
  info: ProjectInfo | null;
}

/** Membre de l'équipe d'un projet — miroir de GET /api/me/projects/{id}/team. */
export interface SessionTeamMember {
  id: number;
  fullName: string | null;
  email: string;
  profileImage: string | null;
  specialties: string[] | null;
  availability: string | null;
}

/** Équipe complète d'un projet (jamais le client) — miroir de GET /api/me/projects/{id}/team. */
export interface SessionProjectTeam {
  owner: SessionTeamMember;
  collaborators: SessionTeamMember[];
}

export type TaskStatus = "todo" | "in_progress" | "done" | "blocked";

/** Tâche de projet — miroir de GET /api/me/projects/{id}/tasks. */
export interface SessionTask {
  id: number;
  title: string;
  description: string | null;
  status: TaskStatus;
  statusLabel: string;
  statusVariant: string;
  dueDate: string | null;
  isOverdue: boolean;
  position: number;
  completedAt: string | null;
  assignee: { id: number; fullName: string | null } | null;
  /** La tâche est assignée à l'utilisateur courant — seule condition pour pouvoir en changer le statut en self-service. */
  isMine: boolean;
}

/** Entrée de temps passé — miroir de GET /api/me/projects/{id}/time-entries. */
export interface SessionTimeEntry {
  id: number;
  user: { id: number; fullName: string | null };
  task: { id: number; title: string } | null;
  minutes: number;
  formattedDuration: string;
  spentOn: string;
  description: string | null;
  isMine: boolean;
}

/** Suivi du temps d'un projet, visible par toute l'équipe. */
export interface SessionTimeTracking {
  entries: SessionTimeEntry[];
  totalMinutes: number;
  formattedTotalTime: string;
  mineMinutes: number;
  mineFormattedTime: string;
}

/** Détail complet d'un devis appartenant à l'utilisateur courant — miroir de GET /api/me/quotes/{id}. */
export interface SessionQuoteDetail {
  id: number;
  category: string;
  categoryDetail: string | null;
  status: string;
  statusLabel: string;
  budget: string | null;
  currency: string | null;
  timeline: string | null;
  channel: string;
  attachmentName: string | null;
  clarifications: { question: string; answer: string }[] | null;
  message: string;
  createdAt: string | null;
  convertedProjectId: number | null;
  convertedProjectTitle: string | null;
}

/** Attributions de l'utilisateur courant selon son rôle (GET /api/me). */
export interface SessionAttributions {
  collaboratingProjects: SessionProject[];
  ownedProjects: SessionProject[];
  clientProjects: SessionProject[];
  quoteRequests: SessionQuote[];
  invoices: SessionInvoice[];
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
  yearsOfExperience: number | null;
  city: string | null;
  linkedinUrl: string | null;
  githubUrl: string | null;
  languages: string[] | null;
  roles: string[];
  isVerified: boolean;
  isTwoFactorEnabled: boolean;
  isCollaborator: boolean;
  /** Complétude du profil "freelance" (0-100), pertinente seulement si isCollaborator. Voir User::getFreelanceProfileCompletionPercentage(). */
  profileCompletion: number;
  /** Clés (parmi FREELANCE_PROFILE_FIELD_KEYS, @see lib/profileFields) encore vides — voir User::getMissingFreelanceProfileFields(). */
  missingProfileFields: string[];
  /** Peut être `null` pour les comptes créés avant l'ajout de ce champ. */
  createdAt: string | null;
  lastLoginAt: string | null;
  lastIp: string | null;
  lastLocation: string | null;
  lastLocationCity: string | null;
  lastLocationCountry: string | null;
  lastLocationLatitude: number | null;
  lastLocationLongitude: number | null;
  lastDevice: string | null;
  /** Type d'appareil lisible ("Smartphone", "Ordinateur"...), dérivé du user-agent — voir App\Service\DeviceParser. */
  lastDeviceType: string;
  lastDeviceBrand: string | null;
  lastDeviceLabel: string;
  editableFields: string[];
  attributions: SessionAttributions;
}

/** Champs auto-modifiables via PATCH /api/me. `phone` est volontairement absent : non modifiable en self-service (cf. MeController::EDITABLE_FIELDS). */
export interface ProfileUpdatePayload {
  fullName?: string;
  bio?: string;
  specialties?: string[];
  availability?: string;
  portfolioUrl?: string;
  yearsOfExperience?: number | null;
  city?: string;
  linkedinUrl?: string;
  githubUrl?: string;
  languages?: string[];
  plainPassword?: string;
  currentPassword?: string;
}
