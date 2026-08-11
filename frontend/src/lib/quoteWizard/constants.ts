import type { QuoteWizardAnswers } from "@/lib/types";

export const TYPE_KEYS = [
  "typeWeb",
  "typeMobile",
  "typeIa",
  "typeCyber",
  "typeAssurance",
  "typeDesign",
  "typeOther",
] as const;
export type TypeKey = (typeof TYPE_KEYS)[number];

export const TYPE_ICONS: Record<TypeKey, string> = {
  typeWeb: "🌐",
  typeMobile: "📱",
  typeIa: "🤖",
  typeCyber: "🛡️",
  typeAssurance: "📋",
  typeDesign: "🎨",
  typeOther: "✏️",
};

/** Les 6 grandes phases du parcours, utilisées pour le fil d'ariane au-dessus de la barre de progression. */
export const PHASE_KEYS = [
  "phaseProject",
  "phaseDiscovery",
  "phaseContext",
  "phaseBudget",
  "phaseContact",
  "phaseReview",
] as const;

export function phaseIndexForStep(step: number): number {
  if (step <= 2) return 0;
  if (step === 3) return 1;
  if (step === 4) return 2;
  if (step <= 6) return 3;
  if (step === 7) return 4;
  return 5;
}

/**
 * Une question de qualification par métier — de vraies questions de pro
 * (dev web/mobile, intégration IA, cybersécurité & gestion des risques,
 * conseil en assurance, design) plutôt qu'un unique champ générique.
 * `typeOther` n'a pas d'options fixes : l'étape 2 devient alors un champ
 * libre optionnel (voir CATEGORY_QUESTION_KEYS).
 */
export const CATEGORY_OPTION_KEYS: Partial<Record<TypeKey, readonly string[]>> = {
  typeWeb: [
    "webSiteVitrine",
    "webEcommerce",
    "webSaas",
    "webBackoffice",
    "webApi",
    "webRefonte",
    "webMaintenance",
    "webAutre",
  ],
  typeMobile: [
    "mobileIos",
    "mobileAndroid",
    "mobileCrossPlatform",
    "mobileRefonte",
    "mobileMaintenance",
    "mobileAutre",
  ],
  typeIa: [
    "iaChatbot",
    "iaAutomatisation",
    "iaAnalyseDonnees",
    "iaGenerationContenu",
    "iaRecommandation",
    "iaAssistantMetier",
    "iaAutre",
  ],
  typeCyber: [
    "cyberAudit",
    "cyberConformite",
    "cyberAcces",
    "cyberIncident",
    "cyberFormation",
    "cyberAutre",
  ],
  typeAssurance: [
    "assuranceAnalyseContrat",
    "assuranceCotation",
    "assuranceMiseEnPlace",
    "assuranceGestionPortefeuille",
    "assuranceSinistre",
    "assuranceConseilRisques",
    "assuranceAutre",
  ],
  typeDesign: [
    "designIdentite",
    "designUiUx",
    "designRefonteSite",
    "designSupportsImprimes",
    "designAutre",
  ],
};

export const CATEGORY_QUESTION_KEYS: Partial<Record<TypeKey, string>> = {
  typeWeb: "step2QuestionWeb",
  typeMobile: "step2QuestionMobile",
  typeIa: "step2QuestionIa",
  typeCyber: "step2QuestionCyber",
  typeAssurance: "step2QuestionAssurance",
  typeDesign: "step2QuestionDesign",
};

export const SOURCE_KEYS = ["sourceGoogle", "sourceSocial", "sourceReco", "sourceOther"] as const;
export const DELAI_KEYS = ["delaiAsap", "delai1Month", "delai3Months", "delaiNone"] as const;
export const CANAL_KEYS = ["canalEmail", "canalWhatsapp", "canalPhone"] as const;
export const CURRENCIES = ["FCFA", "EUR", "USD"] as const;
export type Currency = (typeof CURRENCIES)[number];

export const BUDGET_AMOUNTS: Record<Currency, [string, string, string]> = {
  FCFA: ["500 000", "500 000 – 1 500 000", "1 500 000 – 5 000 000"],
  EUR: ["800", "800 – 2 500", "2 500 – 8 000"],
  USD: ["850", "850 – 2 700", "2 700 – 8 500"],
};

export const TOTAL_STEPS = 8;

export const emptyAnswers: QuoteWizardAnswers = {
  type: "",
  categoryDetail: "",
  source: "",
  description: "",
  fileName: "",
  budget: "",
  currency: "FCFA",
  delai: "",
  name: "",
  email: "",
  phone: "",
  canal: "",
  clarifications: [],
};
