import { useState } from "react";
import type { QuoteWizardAnswers } from "@/lib/types";
import type {
  submitQuoteRequest as submitQuoteRequestFn,
  qualifyQuoteRequest as qualifyQuoteRequestFn,
} from "@/actions/quote";
import {
  CATEGORY_OPTION_KEYS,
  PHASE_KEYS,
  BUDGET_AMOUNTS,
  TOTAL_STEPS,
  emptyAnswers,
  type TypeKey,
  type Currency,
} from "@/lib/quoteWizard/constants";
import { isValidEmail, isValidPhone } from "@/lib/quoteWizard/validators";
import type { WizardStep, IaThreadMessage, WizardT } from "@/lib/quoteWizard/types";

interface UseQuoteWizardOptions {
  /** Pré-remplit le nom/email pour un utilisateur déjà connecté — évite de ressaisir des informations déjà connues. */
  initialName?: string;
  initialEmail?: string;
  t: WizardT;
  /** Locale courante — transmise à l'endroit de qualification IA pour générer les questions dans la bonne langue. */
  locale: string;
  /**
   * Injectés plutôt qu'importés directement : `src/lib/**` n'importe jamais
   * `src/actions/**` ailleurs dans ce projet, et l'injection permet de
   * tester le hook sans mocker le module `@/actions/quote`.
   */
  submitQuoteRequest: typeof submitQuoteRequestFn;
  qualifyQuoteRequest: typeof qualifyQuoteRequestFn;
}

export function useQuoteWizard({
  initialName,
  initialEmail,
  t,
  locale,
  submitQuoteRequest,
  qualifyQuoteRequest,
}: UseQuoteWizardOptions) {
  const [current, setCurrent] = useState<WizardStep>(1);
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
  const [iaThread, setIaThread] = useState<IaThreadMessage[]>([]);
  const [iaAnswer, setIaAnswer] = useState("");
  const [iaLoading, setIaLoading] = useState(false);

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

  /**
   * Tente de générer des questions dynamiques via Claude Sonnet (cf.
   * App\State\QuoteQualifyProcessor) ; retombe sur computeIaQuestions() dès
   * que l'appel échoue ou ne renvoie rien — qualifyQuoteRequest ne throw
   * jamais en pratique (convention Server Action), le catch est un filet
   * défensif supplémentaire.
   */
  async function fetchIaQuestions(): Promise<string[]> {
    try {
      const result = await qualifyQuoteRequest(
        {
          type: answers.type,
          categoryDetail: answers.categoryDetail,
          source: answers.source,
          description: answers.description,
          budget: answers.budget,
          currency: answers.currency,
          delai: answers.delai,
        },
        locale,
      );
      if (result.ok && result.questions.length > 0) return result.questions;
    } catch {
      // filet défensif — cf. docblock ci-dessus
    }
    return computeIaQuestions();
  }

  async function goNext() {
    if (typeof current !== "number") return;
    if (!validate(current)) {
      setShowError(true);
      return;
    }
    setShowError(false);
    if (current === TOTAL_STEPS) {
      if (honeypot.trim().length > 0) return; // anti-bot silencieux — avant tout appel réseau
      setIaLoading(true);
      const qs = await fetchIaQuestions();
      setIaLoading(false);
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

  return {
    current,
    setCurrent,
    answers,
    update,
    categoryKey,
    setCategoryKey,
    showError,
    honeypot,
    setHoneypot,
    consent,
    setConsent,
    submitting,
    submitError,
    iaThread,
    iaAnswer,
    setIaAnswer,
    iaLoading,
    categoryOptionKeys,
    isEmailChannel,
    typeDescriptions,
    phases,
    goNext,
    goBack,
    iaNext,
    handleFinalSubmit,
    budgetOptions,
    iaFinished,
  };
}
