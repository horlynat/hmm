"use client";

import { useState, type ReactNode, type ComponentProps } from "react";
import { useTranslations } from "next-intl";
import clsx from "clsx";
import { Card, ButtonLink, LegalLink } from "@/components/ui";
import { Link } from "@/i18n/navigation";
import { submitQuoteRequest } from "@/actions/quote";
import type { QuoteWizardAnswers } from "@/lib/types";

const TYPE_KEYS = [
  "typeWeb",
  "typeMobile",
  "typeIa",
  "typeCyber",
  "typeAssurance",
  "typeDesign",
  "typeOther",
] as const;
type TypeKey = (typeof TYPE_KEYS)[number];

const TYPE_ICONS: Record<TypeKey, string> = {
  typeWeb: "🌐",
  typeMobile: "📱",
  typeIa: "🤖",
  typeCyber: "🛡️",
  typeAssurance: "📋",
  typeDesign: "🎨",
  typeOther: "✏️",
};

/** Les 6 grandes phases du parcours, utilisées pour le fil d'ariane au-dessus de la barre de progression. */
const PHASE_KEYS = [
  "phaseProject",
  "phaseDiscovery",
  "phaseContext",
  "phaseBudget",
  "phaseContact",
  "phaseReview",
] as const;

function phaseIndexForStep(step: number): number {
  if (step <= 2) return 0;
  if (step === 3) return 1;
  if (step === 4) return 2;
  if (step <= 6) return 3;
  if (step === 7) return 4;
  return 5;
}

/**
 * Une question de qualification par métier — de vraies questions de pro
 * (dev web/mobile, intégration IA, cybersécurité & gestion des risques,
 * conseil en assurance, design) plutôt qu'un unique champ générique.
 * `typeOther` n'a pas d'options fixes : l'étape 2 devient alors un champ
 * libre optionnel (voir CATEGORY_QUESTION_KEYS).
 */
const CATEGORY_OPTION_KEYS: Partial<Record<TypeKey, readonly string[]>> = {
  typeWeb: [
    "webSiteVitrine",
    "webEcommerce",
    "webSaas",
    "webBackoffice",
    "webApi",
    "webRefonte",
    "webMaintenance",
    "webAutre",
  ],
  typeMobile: [
    "mobileIos",
    "mobileAndroid",
    "mobileCrossPlatform",
    "mobileRefonte",
    "mobileMaintenance",
    "mobileAutre",
  ],
  typeIa: [
    "iaChatbot",
    "iaAutomatisation",
    "iaAnalyseDonnees",
    "iaGenerationContenu",
    "iaRecommandation",
    "iaAssistantMetier",
    "iaAutre",
  ],
  typeCyber: [
    "cyberAudit",
    "cyberConformite",
    "cyberAcces",
    "cyberIncident",
    "cyberFormation",
    "cyberAutre",
  ],
  typeAssurance: [
    "assuranceAnalyseContrat",
    "assuranceCotation",
    "assuranceMiseEnPlace",
    "assuranceGestionPortefeuille",
    "assuranceSinistre",
    "assuranceConseilRisques",
    "assuranceAutre",
  ],
  typeDesign: [
    "designIdentite",
    "designUiUx",
    "designRefonteSite",
    "designSupportsImprimes",
    "designAutre",
  ],
};

const CATEGORY_QUESTION_KEYS: Partial<Record<TypeKey, string>> = {
  typeWeb: "step2QuestionWeb",
  typeMobile: "step2QuestionMobile",
  typeIa: "step2QuestionIa",
  typeCyber: "step2QuestionCyber",
  typeAssurance: "step2QuestionAssurance",
  typeDesign: "step2QuestionDesign",
};

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

const TOTAL_STEPS = 8;

const emptyAnswers: QuoteWizardAnswers = {
  type: "",
  categoryDetail: "",
  source: "",
  description: "",
  fileName: "",
  budget: "",
  currency: "FCFA",
  delai: "",
  name: "",
  email: "",
  phone: "",
  canal: "",
  clarifications: [],
};

function OptionCard({
  selected,
  onClick,
  icon,
  description,
  children,
}: {
  selected: boolean;
  onClick: () => void;
  icon?: string;
  description?: string;
  children: ReactNode;
}) {
  return (
    <button
      type="button"
      role="radio"
      aria-checked={selected}
      onClick={onClick}
      className={clsx(
        "flex items-start gap-3 rounded-[var(--radius-md)] border px-4 py-3.5 text-left text-sm font-semibold transition-all",
        selected
          ? "border-brand-primary bg-brand-primary/10 text-brand-primary shadow-sm"
          : "border-[var(--border-soft)] bg-bg-card hover:-translate-y-0.5 hover:border-brand-accent hover:shadow-sm",
      )}
    >
      {icon && (
        <span aria-hidden="true" className="text-lg leading-none">
          {icon}
        </span>
      )}
      <span className="flex flex-col gap-0.5">
        <span>{children}</span>
        {description && (
          <span className={clsx("text-xs font-normal", selected ? "text-brand-primary/80" : "opacity-60")}>
            {description}
          </span>
        )}
      </span>
    </button>
  );
}

function isValidEmail(value: string) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
}

function isValidPhone(value: string) {
  return /^\+?[0-9\s-]{7,20}$/.test(value.trim());
}

interface QuoteWizardProps {
  /** Pré-remplit le nom/email à l'étape 7 pour un utilisateur déjà connecté — évite de ressaisir des informations déjà connues. */
  initialName?: string;
  initialEmail?: string;
  /** Si fourni, un bouton supplémentaire apparaît sur l'écran de succès (ex. retour vers /compte/devis). */
  successHref?: ComponentProps<typeof Link>["href"];
  successLabel?: string;
}

export function QuoteWizard({ initialName, initialEmail, successHref, successLabel }: QuoteWizardProps = {}) {
  const t = useTranslations("contact.wizard");
  const [current, setCurrent] = useState<number | "ia-qualif" | "success">(1);
  const [answers, setAnswers] = useState<QuoteWizardAnswers>({
    ...emptyAnswers,
    name: initialName ?? "",
    email: initialEmail ?? "",
  });
  const [categoryKey, setCategoryKey] = useState<TypeKey | "">("");
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

  const categoryOptionKeys = categoryKey ? CATEGORY_OPTION_KEYS[categoryKey] : undefined;
  const isEmailChannel = answers.canal === t("canalEmail");

  const typeDescriptions: Record<TypeKey, string> = {
    typeWeb: t("typeWebDesc"),
    typeMobile: t("typeMobileDesc"),
    typeIa: t("typeIaDesc"),
    typeCyber: t("typeCyberDesc"),
    typeAssurance: t("typeAssuranceDesc"),
    typeDesign: t("typeDesignDesc"),
    typeOther: t("typeOtherDesc"),
  };
  const phases = PHASE_KEYS.map((key) => ({ key, label: t(key) }));

  function validate(step: number): boolean {
    if (step === 1) return Boolean(answers.type);
    if (step === 2) return categoryOptionKeys ? Boolean(answers.categoryDetail) : true;
    if (step === 3) return Boolean(answers.source);
    if (step === 4) return answers.description.trim().length > 0;
    if (step === 5) return Boolean(answers.budget);
    if (step === 6) return Boolean(answers.delai);
    if (step === 7) {
      const phoneOk = isEmailChannel || isValidPhone(answers.phone);
      return (
        answers.name.trim().length > 0 &&
        isValidEmail(answers.email) &&
        Boolean(answers.canal) &&
        phoneOk
      );
    }
    if (step === 8) return consent;
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
    if (current === TOTAL_STEPS) {
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
      update("clarifications", [
        ...answers.clarifications,
        { question: iaQuestions[iaIndex], answer: value },
      ]);
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
          <div
            role="progressbar"
            aria-label={t("progressLabel")}
            aria-valuemin={1}
            aria-valuemax={TOTAL_STEPS}
            aria-valuenow={current}
            aria-valuetext={t(`step${current}Label` as `step${1 | 2 | 3 | 4 | 5 | 6 | 7 | 8}Label`)}
            className="mb-1.5 flex items-center gap-1.5"
          >
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
          <div aria-live="polite" className="mb-6 flex flex-wrap items-center justify-between gap-x-3 gap-y-1.5">
            <div className="flex flex-wrap items-center gap-x-1.5 gap-y-1 font-mono text-[0.68rem] uppercase tracking-wide">
              {phases.map((phase, i) => {
                const activeIndex = phaseIndexForStep(current);
                return (
                  <span key={phase.key} className="flex items-center gap-1.5">
                    {i > 0 && <span className="text-[var(--border-soft)]">/</span>}
                    <span
                      className={clsx(
                        i === activeIndex
                          ? "font-bold text-brand-primary"
                          : i < activeIndex
                            ? "text-[var(--color-muted)]"
                            : "text-[var(--border-soft)]",
                      )}
                    >
                      {phase.label}
                    </span>
                  </span>
                );
              })}
            </div>
            <span className="shrink-0 font-mono text-xs text-[var(--color-muted)]">
              {t(`step${current}Label` as `step${1 | 2 | 3 | 4 | 5 | 6 | 7 | 8}Label`)}
            </span>
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
          )}

          {current === 2 && (
            <div>
              <div
                className="mb-6 text-xl font-semibold"
                style={{ fontFamily: "var(--font-heading)" }}
              >
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
          )}

          {current === 3 && (
            <fieldset>
              <legend
                className="mb-6 text-xl font-semibold"
                style={{ fontFamily: "var(--font-heading)" }}
              >
                {t("step3Question")}
              </legend>
              <div role="radiogroup" aria-label={t("step3Question")} className="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
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

          {current === 4 && (
            <div>
              <div
                className="mb-6 text-xl font-semibold"
                style={{ fontFamily: "var(--font-heading)" }}
              >
                {t("step4Question")}
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
              <p className="mt-1.5 text-xs text-[var(--color-muted)]">{t("fileHint")}</p>
            </div>
          )}

          {current === 5 && (
            <fieldset>
              <legend
                className="mb-6 text-xl font-semibold"
                style={{ fontFamily: "var(--font-heading)" }}
              >
                {answers.type === t("typeOther")
                  ? t("step5QuestionOther")
                  : t("step5Question")}
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

          {current === 6 && (
            <fieldset>
              <legend
                className="mb-6 text-xl font-semibold"
                style={{ fontFamily: "var(--font-heading)" }}
              >
                {t("step6Question")}
              </legend>
              <div role="radiogroup" aria-label={t("step6Question")} className="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
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

          {current === 7 && (
            <div>
              <div
                className="mb-6 text-xl font-semibold"
                style={{ fontFamily: "var(--font-heading)" }}
              >
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
              <div
                role="radiogroup"
                aria-labelledby="quote-canal-label"
                className="mb-4 grid grid-cols-3 gap-3"
              >
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
          )}

          {current === 8 && (
            <div>
              <div
                className="mb-6 text-xl font-semibold"
                style={{ fontFamily: "var(--font-heading)" }}
              >
                {t("step8Question")}
              </div>
              <div className="mb-6 space-y-4">
                {[
                  {
                    titleKey: "recapSectionProject" as const,
                    step: 1,
                    items: [
                      [t("recapType"), answers.type],
                      [t("recapCategoryDetail"), answers.categoryDetail],
                      [t("recapSource"), answers.source],
                      [t("recapDesc"), answers.description],
                      [t("recapFile"), answers.fileName || t("recapFileNone")],
                    ],
                  },
                  {
                    titleKey: "recapSectionBudget" as const,
                    step: 5,
                    items: [
                      [t("recapBudget"), answers.budget],
                      [t("recapDelai"), answers.delai],
                    ],
                  },
                  {
                    titleKey: "recapSectionContact" as const,
                    step: 7,
                    items: [
                      [t("recapName"), answers.name],
                      [t("recapEmail"), answers.email],
                      [t("recapPhone"), answers.phone || t("recapEmpty")],
                      [t("recapCanal"), answers.canal],
                    ],
                  },
                ].map((section) => (
                  <div
                    key={section.titleKey}
                    className="rounded-[var(--radius-md)] border border-[var(--border-softer)] p-4"
                  >
                    <div className="mb-2 flex items-center justify-between gap-3">
                      <span
                        className="text-xs font-semibold uppercase tracking-wide text-brand-primary"
                        style={{ fontFamily: "var(--font-mono)" }}
                      >
                        {t(section.titleKey)}
                      </span>
                      <button
                        type="button"
                        onClick={() => setCurrent(section.step)}
                        className="text-xs font-semibold text-brand-primary hover:underline"
                      >
                        {t("editStep")}
                      </button>
                    </div>
                    <ul className="list-none divide-y divide-[var(--border-softer)] p-0">
                      {section.items.map(([label, value]) => (
                        <li key={label} className="flex justify-between gap-4 py-2 text-sm">
                          <span className="opacity-60">{label}</span>
                          <span className="max-w-[60%] text-right font-semibold">
                            {value || t("recapEmpty")}
                          </span>
                        </li>
                      ))}
                    </ul>
                  </div>
                ))}
              </div>
              <div className="mb-4 flex flex-wrap gap-2">
                {[
                  ["⏱️", t("trustResponseTime")],
                  ["🔒", t("trustConfidential")],
                  ["🤝", t("trustNoCommitment")],
                ].map(([icon, label]) => (
                  <span
                    key={label}
                    className="inline-flex items-center gap-1.5 rounded-full bg-brand-light/60 px-3 py-1.5 text-xs font-medium text-[var(--color-on-brand-light)]"
                  >
                    <span aria-hidden="true">{icon}</span>
                    {label}
                  </span>
                ))}
              </div>
              <p className="mb-2 text-sm opacity-60">{t("reassurance1")}</p>
              <p className="mb-4 text-sm opacity-60">{t("reassurance2")}</p>
              <label className="flex cursor-pointer items-start gap-2.5 text-sm">
                <input
                  type="checkbox"
                  className="mt-0.5 accent-brand-primary"
                  checked={consent}
                  onChange={(e) => setConsent(e.target.checked)}
                />
                <span>
                  {t.rich("consent", {
                    privacy: (chunks) => (
                      <LegalLink href="/politique-de-confidentialite">{chunks}</LegalLink>
                    ),
                  })}
                </span>
              </label>
            </div>
          )}

          {showError && (
            <p role="alert" className="mt-3 text-sm font-medium text-danger">
              {current === 7 && !isEmailChannel && !isValidPhone(answers.phone)
                ? t("errorPhoneRequired")
                : t("errorRequired")}
            </p>
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
              {current === TOTAL_STEPS ? t("continueWithAssistant") : t("next")}
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
          <div
            role="log"
            aria-live="polite"
            aria-atomic="false"
            className="mb-5 flex max-h-[280px] flex-col gap-2.5 overflow-y-auto"
          >
            {iaThread.map((m, i) => (
              <div key={i} className={clsx("flex items-end gap-2", m.who === "user" && "flex-row-reverse")}>
                {m.who === "bot" && (
                  <span
                    aria-hidden="true"
                    className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-primary/15 text-xs"
                  >
                    🤖
                  </span>
                )}
                <div
                  className={clsx(
                    "max-w-[85%] rounded-[var(--radius-md)] px-3.5 py-2.5 text-sm",
                    m.who === "bot"
                      ? "bg-brand-light text-[var(--color-on-brand-light)]"
                      : "bg-brand-primary text-[var(--color-on-brand-primary)]",
                  )}
                >
                  {m.text}
                </div>
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
            <p role="alert" className="mt-3 text-sm font-medium text-danger">
              {t("errorSubmit")}
            </p>
          )}
        </div>
      )}

      {current === "success" && (
        <div role="status" className="py-4 text-center">
          <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-success/10 text-2xl text-success">
            ✓
          </div>
          <h3
            className="mb-2 text-lg font-semibold"
            style={{ fontFamily: "var(--font-heading)" }}
          >
            {t("successTitle")}
          </h3>
          <p className="mb-6 text-sm opacity-70">{t("successText")}</p>
          <div className="mx-auto max-w-[420px] rounded-[var(--radius-md)] bg-bg-default p-5 text-left">
            <div
              className="mb-3 text-xs font-semibold uppercase tracking-wide text-brand-primary"
              style={{ fontFamily: "var(--font-mono)" }}
            >
              {t("successNextTitle")}
            </div>
            <ol className="list-none space-y-2.5 p-0 text-sm">
              {[t("successStep1"), t("successStep2"), t("successStep3")].map((step, i) => (
                <li key={step} className="flex gap-3">
                  <span className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-brand-primary/15 text-[0.65rem] font-bold text-brand-primary">
                    {i + 1}
                  </span>
                  <span className="opacity-80">{step}</span>
                </li>
              ))}
            </ol>
          </div>
          {successHref && (
            <ButtonLink href={successHref} variant="secondary" className="mt-6 w-fit">
              {successLabel}
            </ButtonLink>
          )}
        </div>
      )}
    </Card>
  );
}
