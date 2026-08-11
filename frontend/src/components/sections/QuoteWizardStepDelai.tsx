import { DELAI_KEYS } from "@/lib/quoteWizard/constants";
import type { UpdateAnswer, WizardT } from "@/lib/quoteWizard/types";
import type { QuoteWizardAnswers } from "@/lib/types";
import { OptionCard } from "./QuoteWizardOptionCard";

interface QuoteWizardStepDelaiProps {
  answers: QuoteWizardAnswers;
  update: UpdateAnswer;
  t: WizardT;
}

export function QuoteWizardStepDelai({ answers, update, t }: QuoteWizardStepDelaiProps) {
  return (
    <fieldset>
      <legend className="mb-6 text-xl font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
        {t("step6Question")}
      </legend>
      <div role="radiogroup" aria-label={t("step6Question")} className="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
        {DELAI_KEYS.map((key) => (
          <OptionCard key={key} selected={answers.delai === t(key)} onClick={() => update("delai", t(key))}>
            {t(key)}
          </OptionCard>
        ))}
      </div>
    </fieldset>
  );
}
