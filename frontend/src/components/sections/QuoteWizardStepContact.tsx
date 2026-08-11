import { CANAL_KEYS } from "@/lib/quoteWizard/constants";
import { isValidEmail, isValidPhone } from "@/lib/quoteWizard/validators";
import type { UpdateAnswer, WizardT } from "@/lib/quoteWizard/types";
import type { QuoteWizardAnswers } from "@/lib/types";
import { OptionCard } from "./QuoteWizardOptionCard";

interface QuoteWizardStepContactProps {
  answers: QuoteWizardAnswers;
  update: UpdateAnswer;
  t: WizardT;
  isEmailChannel: boolean;
  showError: boolean;
}

export function QuoteWizardStepContact({
  answers,
  update,
  t,
  isEmailChannel,
  showError,
}: QuoteWizardStepContactProps) {
  return (
    <div>
      <div className="mb-6 text-xl font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
        {t("step7Question")}
      </div>
      <label className="field-label" htmlFor="quote-name">
        {t("nameLabel")}
      </label>
      <input
        id="quote-name"
        type="text"
        autoComplete="name"
        aria-invalid={showError && answers.name.trim() === "" ? true : undefined}
        className="input mb-4"
        placeholder={t("namePlaceholder")}
        value={answers.name}
        onChange={(e) => update("name", e.target.value)}
      />
      <label className="field-label" htmlFor="quote-email">
        {t("emailLabel")}
      </label>
      <input
        id="quote-email"
        type="email"
        autoComplete="email"
        inputMode="email"
        aria-invalid={showError && !isValidEmail(answers.email) ? true : undefined}
        className="input mb-4"
        placeholder={t("emailPlaceholder")}
        value={answers.email}
        onChange={(e) => update("email", e.target.value)}
      />
      <span className="field-label" id="quote-canal-label">
        {t("canalLabel")}
      </span>
      <div role="radiogroup" aria-labelledby="quote-canal-label" className="mb-4 grid grid-cols-3 gap-3">
        {CANAL_KEYS.map((key) => (
          <OptionCard key={key} selected={answers.canal === t(key)} onClick={() => update("canal", t(key))}>
            {t(key)}
          </OptionCard>
        ))}
      </div>
      {!isEmailChannel && (
        <>
          <label className="field-label" htmlFor="quote-phone">
            {t("phoneLabel")}
          </label>
          <input
            id="quote-phone"
            type="tel"
            autoComplete="tel"
            inputMode="tel"
            aria-invalid={showError && !isValidPhone(answers.phone) ? true : undefined}
            className="input mb-1.5"
            placeholder={t("phonePlaceholder")}
            value={answers.phone}
            onChange={(e) => update("phone", e.target.value)}
          />
          <p className="text-xs text-[var(--color-muted)]">{t("phoneHint")}</p>
        </>
      )}
    </div>
  );
}
