import type { UpdateAnswer, WizardT } from "@/lib/quoteWizard/types";
import type { QuoteWizardAnswers } from "@/lib/types";

interface QuoteWizardStepDescriptionProps {
  answers: QuoteWizardAnswers;
  update: UpdateAnswer;
  t: WizardT;
}

export function QuoteWizardStepDescription({ answers, update, t }: QuoteWizardStepDescriptionProps) {
  return (
    <div>
      <div className="mb-4 text-lg font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
        {t("step4Question")}
      </div>
      <textarea
        className="input mb-3 min-h-[120px]"
        placeholder={t("descPlaceholder")}
        value={answers.description}
        onChange={(e) => update("description", e.target.value)}
      />
      <label className="field-label" htmlFor="quote-file">
        {t("fileLabel")}
      </label>
      <input
        id="quote-file"
        type="file"
        className="input"
        onChange={(e) => update("fileName", e.target.files?.[0]?.name ?? "")}
      />
      <p className="mt-1.5 text-xs text-[var(--color-muted)]">{t("fileHint")}</p>
    </div>
  );
}
