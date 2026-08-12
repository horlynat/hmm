import type { useTranslations } from "next-intl";
import type { QuoteWizardAnswers } from "@/lib/types";

export type WizardStep = number | "ia-qualif" | "success";

export type IaThreadMessage = { who: "bot" | "user"; text: string };

export type WizardT = ReturnType<typeof useTranslations>;

/** Setter générique partagé par le hook et chaque composant d'étape. */
export type UpdateAnswer = <K extends keyof QuoteWizardAnswers>(
  key: K,
  value: QuoteWizardAnswers[K],
) => void;
