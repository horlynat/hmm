import { SOURCE_KEYS } from "@/lib/quoteWizard/constants";
import type { UpdateAnswer, WizardT } from "@/lib/quoteWizard/types";
import type { QuoteWizardAnswers } from "@/lib/types";
import { OptionCard } from "./QuoteWizardOptionCard";

interface QuoteWizardStepSourceProps {
  answers: QuoteWizardAnswers;
  update: UpdateAnswer;
  t: WizardT;
}

export function QuoteWizardStepSource({ answers, update, t }: QuoteWizardStepSourceProps) {
  return (
    <fieldset>
      <legend className="mb-6 text-xl font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
        {t("step3Question")}
      </legend>
      <div role="radiogroup" aria-label={t("step3Question")} className="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
        {SOURCE_KEYS.map((key) => (
          <OptionCard key={key} selected={answers.source === t(key)} onClick={() => update("source", t(key))}>
            {t(key)}
          </OptionCard>
        ))}
      </div>
    </fieldset>
  );
}
