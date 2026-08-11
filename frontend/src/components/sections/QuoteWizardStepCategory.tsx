import { CATEGORY_QUESTION_KEYS, type TypeKey } from "@/lib/quoteWizard/constants";
import type { UpdateAnswer, WizardT } from "@/lib/quoteWizard/types";
import type { QuoteWizardAnswers } from "@/lib/types";
import { OptionCard } from "./QuoteWizardOptionCard";

interface QuoteWizardStepCategoryProps {
  answers: QuoteWizardAnswers;
  update: UpdateAnswer;
  t: WizardT;
  categoryKey: TypeKey | "";
  categoryOptionKeys: readonly string[] | undefined;
}

export function QuoteWizardStepCategory({
  answers,
  update,
  t,
  categoryKey,
  categoryOptionKeys,
}: QuoteWizardStepCategoryProps) {
  return (
    <div>
      <div className="mb-6 text-xl font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
        {categoryOptionKeys
          ? t(CATEGORY_QUESTION_KEYS[categoryKey as TypeKey] as "step2QuestionWeb")
          : t("step2QuestionOther")}
      </div>
      {categoryOptionKeys ? (
        <div
          role="radiogroup"
          aria-label={t(CATEGORY_QUESTION_KEYS[categoryKey as TypeKey] as "step2QuestionWeb")}
          className="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2"
        >
          {categoryOptionKeys.map((key) => (
            <OptionCard
              key={key}
              selected={answers.categoryDetail === t(key as "webAutre")}
              onClick={() => update("categoryDetail", t(key as "webAutre"))}
            >
              {t(key as "webAutre")}
            </OptionCard>
          ))}
        </div>
      ) : (
        <textarea
          className="input mb-4 min-h-[90px]"
          placeholder={t("step2PlaceholderOther")}
          value={answers.categoryDetail}
          onChange={(e) => update("categoryDetail", e.target.value)}
        />
      )}
    </div>
  );
}
