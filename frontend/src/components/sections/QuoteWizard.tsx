"use client";

import { useState, type ReactNode } from "react";
import { useTranslations } from "next-intl";
import clsx from "clsx";
import { Card } from "@/components/ui";
import { submitQuoteRequest } from "@/actions/quote";
import type { QuoteWizardAnswers } from "@/lib/types";

const TYPE_KEYS = [
  "typeWeb",
  "typeMobile",
  "typeIa",
  "typeCyber",
  "typeDesign",
  "typeOther",
] as const;
const SOURCE_KEYS = ["sourceGoogle", "sourceSocial", "sourceReco", "sourceOther"] as const;
const DELAI_KEYS = ["delaiAsap", "delai1Month", "delai3Months", "delaiNone"] as const;
const CANAL_KEYS = ["canalEmail", "canalWhatsapp", "canalPhone"] as const;
const CURRENCIES = ["FCFA", "EUR", "USD"] as const;
type Currency = (typeof CURRENCIES)[number];

const BUDGET_AMOUNTS: Record<Currency, [string, string, string]> = {
  FCFA: ["500 000", "500 000 – 1 500 000", "1 500 000 – 5 000 000"],
  EUR: ["800", "800 – 2 500", "2 500 – 8 000"],
  USD: ["850", "850 – 2 700", "2 700 – 8 500"],
};

const TOTAL_STEPS = 7;

const emptyAnswers: QuoteWizardAnswers = {
  type: "",
  source: "",
  description: "",
  fileName: "",
  budget: "",
  currency: "FCFA",
  delai: "",
  name: "",
  email: "",
  canal: "",
  clarifications: [],
};

function OptionCard({
  selected,
  onClick,
  children,
}: {
  selected: boolean;
  onClick: () => void;
  children: ReactNode;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={clsx(
        "rounded-[var(--radius-md)] border px-4 py-3.5 text-left text-sm font-semibold transition-colors",
        selected
          ? "border-brand-primary bg-brand-primary/10 text-brand-primary"
          : "border-[var(--border-soft)] bg-bg-card hover:border-brand-accent",
      )}
    >
      {children}
    </button>
  );
}

function isValidEmail(value: string) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
}

export function QuoteWizard() {
  const t = useTranslations("contact.wizard");
  const [current, setCurrent] = useState<number | "ia-qualif" | "success">(1);
  const [answers, setAnswers] = useState<QuoteWizardAnswers>(emptyAnswers);
  const [showError, setShowError] = useState(false);
  const [honeypot, setHoneypot] = useState("");
  const [consent, setConsent] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState(false);

  const [iaQuestions, setIaQuestions] = useState<string[]>([]);
  const [iaIndex, setIaIndex] = useState(0);
  const [iaThread, setIaThread] = useState<{ who: "bot" | "user"; text: string }[]>([]);
  const [iaAnswer, setIaAnswer] = useState("");

  function update<K extends keyof QuoteWizardAnswers>(
    key: K,
    value: QuoteWizardAnswers[K],
  ) {
    setAnswers((prev) => ({ ...prev, [key]: value }));
  }

  function validate(step: number): boolean {
    if (step === 1) return Boolean(answers.type);
    if (step === 2) return Boolean(answers.source);
    if (step === 3) return answers.description.trim().length > 0;
    if (step === 4) return Boolean(answers.budget);
    if (step === 5) return Boolean(answers.delai);
    if (step === 6) return answers.name.trim().length > 0 && isValidEmail(answers.email);
    if (step === 7) return consent;
    return true;
  }

  function computeIaQuestions(): string[] {
    const qs: string[] = [];
    if (answers.type === t("typeOther")) {
      qs.push(t("iaQuestionOther"));
    } else if (answers.budget === t("budgetToDefine")) {
      qs.push(t("iaQuestionBudget"));
    } else {
      qs.push(t("iaQuestionDefault"));
    }
    qs.push(t("iaQuestionLast"));
    return qs;
  }

  function goNext() {
    if (typeof current !== "number") return;
    if (!validate(current)) {
      setShowError(true);
      return;
    }
    setShowError(false);
    if (current === 7) {
      if (honeypot.trim().length > 0) return; // anti-bot silencieux
      const qs = computeIaQuestions();
      setIaQuestions(qs);
      setIaIndex(0);
      setIaThread([{ who: "bot", text: qs[0] }]);
      setCurrent("ia-qualif");
      return;
    }
    setCurrent(current + 1);
  }

  function goBack() {
    if (typeof current !== "number") return;
    setShowError(false);
    setCurrent(Math.max(current - 1, 1));
  }

  function iaNext(skip: boolean) {
    const value = iaAnswer.trim();
    if (!skip && value) {
      setIaThread((prev) => [...prev, { who: "user", text: value }]);
      update("clarifications", [...answers.clarifications, value]);
    }
    setIaAnswer("");
    const nextIndex = iaIndex + 1;
    setIaIndex(nextIndex);
    if (nextIndex < iaQuestions.length) {
      setIaThread((prev) => [...prev, { who: "bot", text: iaQuestions[nextIndex] }]);
    } else {
      setIaThread((prev) => [...prev, { who: "bot", text: t("iaThanks") }]);
    }
  }

  async function handleFinalSubmit() {
    setSubmitting(true);
    setSubmitError(false);
    const result = await submitQuoteRequest(answers);
    setSubmitting(false);
    if (result.ok) {
      setCurrent("success");
    } else {
      setSubmitError(true);
    }
  }

  const amounts = BUDGET_AMOUNTS[answers.currency as Currency] ?? BUDGET_AMOUNTS.FCFA;
  const budgetOptions = [
    `${t("budgetLow")} ${amounts[0]} ${answers.currency}`,
    `${amounts[1]} ${answers.currency}`,
    `${amounts[2]} ${answers.currency}`,
    t("budgetToDefine"),
  ];
  const iaFinished = iaIndex >= iaQuestions.length;

  return (
    <Card variant="soft" className="mx-auto max-w-[640px] p-8">
      {typeof current === "number" && (
        <>
          <div className="mb-1.5 flex items-center gap-1.5">
            {Array.from({ length: TOTAL_STEPS }).map((_, i) => (
              <div
                key={i}
                className={clsx(
                  "h-[5px] flex-1 rounded-full transition-colors",
                  i + 1 < current
                    ? "bg-brand-accent"
                    : i + 1 === current
                      ? "bg-brand-primary"
                      : "bg-[var(--border-soft)]",
                )}
              />
            ))}
          </div>
          <div className="mb-6 text-right font-mono text-xs opacity-55">
            {t(`step${current}Label` as `step${1 | 2 | 3 | 4 | 5 | 6 | 7}Label`)}
          </div>

          {/* Champ piège anti-bot, invisible pour un humain */}
          <div className="absolute h-0 w-0 overflow-hidden opacity-0" aria-hidden="true">
            <label htmlFor="quote-hp">Ne pas remplir ce champ</label>
            <input
              id="quote-hp"
              type="text"
              tabIndex={-1}
              autoComplete="off"
              value={honeypot}
              onChange={(e) => setHoneypot(e.target.value)}
            />
          </div>

          {current === 1 && (
            <fieldset>
              <legend
                className="mb-6 text-xl font-semibold"
                style={{ fontFamily: "var(--font-heading)" }}
              >
                {t("step1Question")}
              </legend>
              <div className="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                {TYPE_KEYS.map((key) => (
                  <OptionCard
                    key={key}
                    selected={answers.type === t(key)}
                    onClick={() => update("type", t(key))}
                  >
                    {t(key)}
                  </OptionCard>
                ))}
              </div>
            </fieldset>
          )}

          {current === 2 && (
            <fieldset>
              <legend
                className="mb-6 text-xl font-semibold"
                style={{ fontFamily: "var(--font-heading)" }}
              >
                {t("step2Question")}
              </legend>
              <div className="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                {SOURCE_KEYS.map((key) => (
                  <OptionCard
                    key={key}
                    selected={answers.source === t(key)}
                    onClick={() => update("source", t(key))}
                  >
                    {t(key)}
                  </OptionCard>
                ))}
              </div>
            </fieldset>
          )}

          {current === 3 && (
            <div>
              <div
                className="mb-6 text-xl font-semibold"
                style={{ fontFamily: "var(--font-heading)" }}
              >
                {t("step3Question")}
              </div>
              <textarea
                className="input mb-4 min-h-[120px]"
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
                onChange={(e) =>
                  update("fileName", e.target.files?.[0]?.name ?? "")
                }
              />
              <p className="mt-1.5 text-xs opacity-55">{t("fileHint")}</p>
            </div>
          )}

          {current === 4 && (
            <fieldset>
              <legend
                className="mb-6 text-xl font-semibold"
                style={{ fontFamily: "var(--font-heading)" }}
              >
                {answers.type === t("typeOther")
                  ? t("step4QuestionOther")
                  : t("step4Question")}
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
              <div className="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                {budgetOptions.map((label) => (
                  <OptionCard
                    key={label}
                    selected={answers.budget === label}
                    onClick={() => update("budget", label)}
                  >
                    {label}
                  </OptionCard>
                ))}
              </div>
            </fieldset>
          )}

          {current === 5 && (
            <fieldset>
              <legend
                className="mb-6 text-xl font-semibold"
                style={{ fontFamily: "var(--font-heading)" }}
              >
                {t("step5Question")}
              </legend>
              <div className="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                {DELAI_KEYS.map((key) => (
                  <OptionCard
                    key={key}
                    selected={answers.delai === t(key)}
                    onClick={() => update("delai", t(key))}
                  >
                    {t(key)}
                  </OptionCard>
                ))}
              </div>
            </fieldset>
          )}

          {current === 6 && (
            <div>
              <div
                className="mb-6 text-xl font-semibold"
                style={{ fontFamily: "var(--font-heading)" }}
              >
                {t("step6Question")}
              </div>
              <label className="field-label" htmlFor="quote-name">
                {t("nameLabel")}
              </label>
              <input
                id="quote-name"
                type="text"
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
                className="input mb-4"
                placeholder={t("emailPlaceholder")}
                value={answers.email}
                onChange={(e) => update("email", e.target.value)}
              />
              <span className="field-label">{t("canalLabel")}</span>
              <div className="grid grid-cols-3 gap-3">
                {CANAL_KEYS.map((key) => (
                  <OptionCard
                    key={key}
                    selected={answers.canal === t(key)}
                    onClick={() => update("canal", t(key))}
                  >
                    {t(key)}
                  </OptionCard>
                ))}
              </div>
            </div>
          )}

          {current === 7 && (
            <div>
              <div
                className="mb-6 text-xl font-semibold"
                style={{ fontFamily: "var(--font-heading)" }}
              >
                {t("step7Question")}
              </div>
              <ul className="mb-6 list-none divide-y divide-[var(--border-softer)] p-0">
                {[
                  [t("recapType"), answers.type],
                  [t("recapSource"), answers.source],
                  [t("recapDesc"), answers.description],
                  [t("recapFile"), answers.fileName || t("recapFileNone")],
                  [t("recapBudget"), answers.budget],
                  [t("recapDelai"), answers.delai],
                  [t("recapName"), answers.name],
                  [t("recapEmail"), answers.email],
                  [t("recapCanal"), answers.canal],
                ].map(([label, value]) => (
                  <li key={label} className="flex justify-between gap-4 py-2.5 text-sm">
                    <span className="opacity-60">{label}</span>
                    <span className="max-w-[60%] text-right font-semibold">
                      {value || t("recapEmpty")}
                    </span>
                  </li>
                ))}
              </ul>
              <p className="mb-2 text-sm opacity-60">{t("reassurance1")}</p>
              <p className="mb-4 text-sm opacity-60">{t("reassurance2")}</p>
              <label className="flex cursor-pointer items-start gap-2.5 text-sm">
                <input
                  type="checkbox"
                  className="mt-0.5 accent-brand-primary"
                  checked={consent}
                  onChange={(e) => setConsent(e.target.checked)}
                />
                {t("consent")}
              </label>
            </div>
          )}

          {showError && (
            <p className="mt-3 text-sm text-danger">{t("errorRequired")}</p>
          )}

          <div className="mt-8 flex items-center justify-between">
            <button
              type="button"
              onClick={goBack}
              className={clsx("btn-secondary", current === 1 && "invisible")}
            >
              {t("back")}
            </button>
            <button type="button" onClick={goNext} className="btn-primary">
              {current === 7 ? t("continueWithAssistant") : t("next")}
            </button>
          </div>
        </>
      )}

      {current === "ia-qualif" && (
        <div>
          <div className="mb-1 font-mono text-xs uppercase tracking-wide text-brand-primary">
            {t("iaLabel")}
          </div>
          <div
            className="mb-6 text-xl font-semibold"
            style={{ fontFamily: "var(--font-heading)" }}
          >
            {t("iaQuestion")}
          </div>
          <div className="mb-5 flex max-h-[280px] flex-col gap-2.5 overflow-y-auto">
            {iaThread.map((m, i) => (
              <div
                key={i}
                className={clsx(
                  "max-w-[85%] rounded-[var(--radius-md)] px-3.5 py-2.5 text-sm",
                  m.who === "bot"
                    ? "self-start bg-brand-light text-brand-dark"
                    : "ml-auto bg-brand-primary text-white",
                )}
              >
                {m.text}
              </div>
            ))}
          </div>
          {!iaFinished && (
            <>
              <textarea
                className="input mb-4 min-h-[70px]"
                placeholder={t("iaAnswerPlaceholder")}
                value={iaAnswer}
                onChange={(e) => setIaAnswer(e.target.value)}
              />
              <div className="flex justify-end gap-3">
                <button
                  type="button"
                  className="btn-secondary"
                  onClick={() => iaNext(true)}
                >
                  {t("iaSkip")}
                </button>
                <button
                  type="button"
                  className="btn-primary"
                  onClick={() => iaNext(false)}
                >
                  {t("iaAnswer")}
                </button>
              </div>
            </>
          )}
          {iaFinished && (
            <div className="flex justify-end">
              <button
                type="button"
                className="btn-primary"
                disabled={submitting}
                onClick={handleFinalSubmit}
              >
                {submitting ? t("iaAnswer") : t("sendRequest")}
              </button>
            </div>
          )}
          {submitError && (
            <p className="mt-3 text-sm text-danger">{t("errorSubmit")}</p>
          )}
        </div>
      )}

      {current === "success" && (
        <div className="py-4 text-center">
          <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-success/10 text-2xl text-success">
            ✓
          </div>
          <h3
            className="mb-2 text-lg font-semibold"
            style={{ fontFamily: "var(--font-heading)" }}
          >
            {t("successTitle")}
          </h3>
          <p className="text-sm opacity-70">{t("successText")}</p>
        </div>
      )}
    </Card>
  );
}
