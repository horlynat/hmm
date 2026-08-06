/**
 * Champs qui composent la complétude du profil freelance — miroir exact de
 * User::freelanceProfileFields() côté backend. Centralisé ici pour que la
 * bannière (gestion-projet) et la checklist (profil) restent en phase avec
 * `SessionUser.missingProfileFields`, qui utilise ces mêmes clés.
 */
export const FREELANCE_PROFILE_FIELD_KEYS = [
  "fullName",
  "bio",
  "specialties",
  "availability",
  "portfolioUrl",
  "yearsOfExperience",
  "city",
  "professionalLinks",
  "languages",
] as const;

export type FreelanceProfileFieldKey = (typeof FREELANCE_PROFILE_FIELD_KEYS)[number];

/** Clé de traduction `auth.profile.*` correspondant à chaque champ — pour un libellé lisible dans la checklist/bannière. */
export const FREELANCE_PROFILE_FIELD_LABEL_KEYS: Record<FreelanceProfileFieldKey, string> = {
  fullName: "fullNameLabel",
  bio: "bioLabel",
  specialties: "specialtiesLabel",
  availability: "availabilityLabel",
  portfolioUrl: "portfolioLabel",
  yearsOfExperience: "yearsOfExperienceLabel",
  city: "cityLabel",
  professionalLinks: "professionalLinksLabel",
  languages: "languagesLabel",
};

/**
 * Compétences autorisées — liste fermée (pas de texte libre), miroir exact
 * de MeController::ALLOWED_SPECIALTIES. Mêmes clés de traduction que le
 * formulaire d'inscription public (namespace `freelances.signup`), pour un
 * seul et même vocabulaire dans toute l'app.
 */
export const SPECIALTY_KEYS = [
  "specialtyBackend",
  "specialtyFrontend",
  "specialtyMobile",
  "specialtyAi",
  "specialtyCyber",
  "specialtyDesign",
] as const;

export const SPECIALTY_ICONS: Record<(typeof SPECIALTY_KEYS)[number], string> = {
  specialtyBackend: "🗄️",
  specialtyFrontend: "💻",
  specialtyMobile: "📱",
  specialtyAi: "🤖",
  specialtyCyber: "🛡️",
  specialtyDesign: "🎨",
};

/** Langues autorisées — liste fermée, miroir exact de MeController::ALLOWED_LANGUAGES. */
export const LANGUAGE_KEYS = ["languageFrench", "languageEnglish", "languageSpanish", "languagePortuguese"] as const;
