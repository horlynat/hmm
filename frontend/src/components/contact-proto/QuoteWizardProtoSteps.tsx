import { CANAL_KEYS, CATEGORY_QUESTION_KEYS, CURRENCIES, DELAI_KEYS, SOURCE_KEYS, TYPE_KEYS, type TypeKey } from "@/lib/quoteWizard/constants";
import { isValidEmail, isValidPhone } from "@/lib/quoteWizard/validators";
import type { UpdateAnswer, WizardT } from "@/lib/quoteWizard/types";
import type { QuoteWizardAnswers } from "@/lib/types";
import { OptionTile } from "./OptionTile";
import { TYPE_ICONS_PROTO } from "./typeIcons";

/**
 * Étapes 1 à 7 du questionnaire guidé — markup du prototype (`.qq .tiles
 * .tile`), logique identique aux `QuoteWizardStep*` existants (mêmes clés de
 * traduction, mêmes validations) : seul l'habillage change.
 */
type StepProps = { answers: QuoteWizardAnswers; update: UpdateAnswer; t: WizardT };

export function StepType({
  answers,
  update,
  t,
  typeDescriptions,
  setCategoryKey,
}: StepProps & { typeDescriptions: Record<TypeKey, string>; setCategoryKey: (key: TypeKey) => void }) {
  return (
    <div>
      <h3 className="qq" tabIndex={-1} id="quote-type-label">
        {t("step1Question")}
      </h3>
      <div className="tiles tb" role="radiogroup" aria-labelledby="quote-type-label">
        {TYPE_KEYS.map((key) => (
          <OptionTile
            key={key}
            selected={answers.type === t(key)}
            icon={TYPE_ICONS_PROTO[key]}
            description={typeDescriptions[key]}
            onClick={() => {
              update("type", t(key));
              update("categoryDetail", "");
              setCategoryKey(key);
            }}
          >
            {t(key)}
          </OptionTile>
        ))}
      </div>
    </div>
  );
}

export function StepCategory({
  answers,
  update,
  t,
  categoryKey,
  categoryOptionKeys,
}: StepProps & { categoryKey: TypeKey | ""; categoryOptionKeys: readonly string[] | undefined }) {
  const question = categoryOptionKeys ? t(CATEGORY_QUESTION_KEYS[categoryKey as TypeKey] as "step2QuestionWeb") : t("step2QuestionOther");
  return (
    <div>
      <h3 className="qq" tabIndex={-1} id="quote-category-label">
        {question}
      </h3>
      {categoryOptionKeys ? (
        <div className="tiles" role="radiogroup" aria-labelledby="quote-category-label">
          {categoryOptionKeys.map((key) => (
            <OptionTile key={key} selected={answers.categoryDetail === t(key as "webAutre")} onClick={() => update("categoryDetail", t(key as "webAutre"))}>
              {t(key as "webAutre")}
            </OptionTile>
          ))}
        </div>
      ) : (
        <textarea
          aria-labelledby="quote-category-label"
          className="fi"
          style={{ minHeight: "100px" }}
          placeholder={t("step2PlaceholderOther")}
          value={answers.categoryDetail}
          onChange={(e) => update("categoryDetail", e.target.value)}
        />
      )}
    </div>
  );
}

export function StepSource({ answers, update, t }: StepProps) {
  return (
    <div>
      <h3 className="qq" tabIndex={-1} id="quote-source-label">
        {t("step3Question")}
      </h3>
      <div className="tiles" role="radiogroup" aria-labelledby="quote-source-label">
        {SOURCE_KEYS.map((key) => (
          <OptionTile key={key} selected={answers.source === t(key)} onClick={() => update("source", t(key))}>
            {t(key)}
          </OptionTile>
        ))}
      </div>
    </div>
  );
}

export function StepDescription({ answers, update, t }: StepProps) {
  return (
    <div>
      <h3 className="qq" tabIndex={-1} id="quote-desc-label">
        {t("step4Question")}
      </h3>
      <textarea
        aria-labelledby="quote-desc-label"
        className="fi"
        style={{ minHeight: "130px" }}
        placeholder={t("descPlaceholder")}
        value={answers.description}
        onChange={(e) => update("description", e.target.value)}
      />
      <div className="fl2" style={{ marginTop: "16px" }}>
        <label htmlFor="quote-file">{t("fileLabel")}</label>
        <input id="quote-file" type="file" className="fi" onChange={(e) => update("fileName", e.target.files?.[0]?.name ?? "")} />
        <small className="dm">{t("fileHint")}</small>
      </div>
    </div>
  );
}

export function StepBudget({ answers, update, t, budgetOptions }: StepProps & { budgetOptions: string[] }) {
  return (
    <div>
      <h3 className="qq" tabIndex={-1} id="quote-budget-label">
        {answers.type === t("typeOther") ? t("step5QuestionOther") : t("step5Question")}
      </h3>
      <div className="fl2" style={{ maxWidth: "220px", marginBottom: "16px" }}>
        <label htmlFor="quote-currency">{t("currencyLabel")}</label>
        <select
          id="quote-currency"
          className="fi"
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
      <div className="tiles" role="radiogroup" aria-labelledby="quote-budget-label">
        {budgetOptions.map((label) => (
          <OptionTile key={label} selected={answers.budget === label} onClick={() => update("budget", label)}>
            {label}
          </OptionTile>
        ))}
      </div>
    </div>
  );
}

export function StepDelai({ answers, update, t }: StepProps) {
  return (
    <div>
      <h3 className="qq" tabIndex={-1} id="quote-delai-label">
        {t("step6Question")}
      </h3>
      <div className="tiles" role="radiogroup" aria-labelledby="quote-delai-label">
        {DELAI_KEYS.map((key) => (
          <OptionTile key={key} selected={answers.delai === t(key)} onClick={() => update("delai", t(key))}>
            {t(key)}
          </OptionTile>
        ))}
      </div>
    </div>
  );
}

export function StepContact({ answers, update, t, isEmailChannel, showError }: StepProps & { isEmailChannel: boolean; showError: boolean }) {
  return (
    <div>
      <h3 className="qq" tabIndex={-1}>
        {t("step7Question")}
      </h3>
      <div className="fl2" id="f-qname">
        <label htmlFor="quote-name">{t("nameLabel")}</label>
        <input
          id="quote-name"
          type="text"
          autoComplete="name"
          className="fi"
          aria-invalid={showError && answers.name.trim() === "" ? true : undefined}
          placeholder={t("namePlaceholder")}
          value={answers.name}
          onChange={(e) => update("name", e.target.value)}
        />
      </div>
      <div className="fl2" id="f-qemail">
        <label htmlFor="quote-email">{t("emailLabel")}</label>
        <input
          id="quote-email"
          type="email"
          autoComplete="email"
          inputMode="email"
          className="fi"
          aria-invalid={showError && !isValidEmail(answers.email) ? true : undefined}
          placeholder={t("emailPlaceholder")}
          value={answers.email}
          onChange={(e) => update("email", e.target.value)}
        />
      </div>
      <p className="fl2" id="quote-canal-label" style={{ marginBottom: "6px" }}>
        {t("canalLabel")}
      </p>
      <div className="tiles" role="radiogroup" aria-labelledby="quote-canal-label" style={{ gridTemplateColumns: "repeat(3,minmax(0,1fr))", marginBottom: "16px" }}>
        {CANAL_KEYS.map((key) => (
          <OptionTile key={key} selected={answers.canal === t(key)} onClick={() => update("canal", t(key))}>
            {t(key)}
          </OptionTile>
        ))}
      </div>
      {!isEmailChannel && (
        <div className="fl2" id="f-qphone">
          <label htmlFor="quote-phone">{t("phoneLabel")}</label>
          <input
            id="quote-phone"
            type="tel"
            autoComplete="tel"
            inputMode="tel"
            className="fi"
            aria-invalid={showError && !isValidPhone(answers.phone) ? true : undefined}
            placeholder={t("phonePlaceholder")}
            value={answers.phone}
            onChange={(e) => update("phone", e.target.value)}
          />
          <small className="dm">{t("phoneHint")}</small>
        </div>
      )}
    </div>
  );
}
