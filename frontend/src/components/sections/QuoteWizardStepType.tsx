import { TYPE_KEYS, TYPE_ICONS, type TypeKey } from "@/lib/quoteWizard/constants";
import type { UpdateAnswer, WizardT } from "@/lib/quoteWizard/types";
import type { QuoteWizardAnswers } from "@/lib/types";
import { OptionCard } from "./QuoteWizardOptionCard";

interface QuoteWizardStepTypeProps {
  answers: QuoteWizardAnswers;
  update: UpdateAnswer;
  t: WizardT;
  typeDescriptions: Record<TypeKey, string>;
  setCategoryKey: (key: TypeKey) => void;
}

export function QuoteWizardStepType({
  answers,
  update,
  t,
  typeDescriptions,
  setCategoryKey,
}: QuoteWizardStepTypeProps) {
  return (
    <fieldset>
      <legend className="mb-6 text-xl font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
        {t("step1Question")}
      </legend>
      <div role="radiogroup" aria-label={t("step1Question")} className="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
        {TYPE_KEYS.map((key) => (
          <OptionCard
            key={key}
            selected={answers.type === t(key)}
            icon={TYPE_ICONS[key]}
            description={typeDescriptions[key]}
            onClick={() => {
              update("type", t(key));
              update("categoryDetail", "");
              setCategoryKey(key);
            }}
          >
            {t(key)}
          </OptionCard>
        ))}
      </div>
    </fieldset>
  );
}
