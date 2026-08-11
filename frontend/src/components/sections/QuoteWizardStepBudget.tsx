import { CURRENCIES } from "@/lib/quoteWizard/constants";
import type { UpdateAnswer, WizardT } from "@/lib/quoteWizard/types";
import type { QuoteWizardAnswers } from "@/lib/types";
import { OptionCard } from "./QuoteWizardOptionCard";

interface QuoteWizardStepBudgetProps {
  answers: QuoteWizardAnswers;
  update: UpdateAnswer;
  t: WizardT;
  budgetOptions: string[];
}

export function QuoteWizardStepBudget({ answers, update, t, budgetOptions }: QuoteWizardStepBudgetProps) {
  return (
    <fieldset>
      <legend className="mb-6 text-xl font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
        {answers.type === t("typeOther") ? t("step5QuestionOther") : t("step5Question")}
      </legend>
      <div className="mb-4 max-w-[220px]">
        <label className="field-label" htmlFor="quote-currency">
          {t("currencyLabel")}
        </label>
        <select
          id="quote-currency"
          className="input"
          value={answers.currency}
          onChange={(e) => {
            update("currency", e.target.value);
            update("budget", "");
          }}
        >
          {CURRENCIES.map((c) => (
            <option key={c} value={c}>
              {c}
            </option>
          ))}
        </select>
      </div>
      <div role="radiogroup" aria-label={t("step5Question")} className="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
        {budgetOptions.map((label) => (
          <OptionCard key={label} selected={answers.budget === label} onClick={() => update("budget", label)}>
            {label}
          </OptionCard>
        ))}
      </div>
    </fieldset>
  );
}
