"use client";

import type { ComponentProps } from "react";
import { useTranslations, useLocale } from "next-intl";
import clsx from "clsx";
import { Card } from "@/components/ui";
import { Link } from "@/i18n/navigation";
import { submitQuoteRequest, qualifyQuoteRequest } from "@/actions/quote";
import { TOTAL_STEPS } from "@/lib/quoteWizard/constants";
import { isValidPhone } from "@/lib/quoteWizard/validators";
import { useQuoteWizard } from "@/lib/quoteWizard/useQuoteWizard";
import { QuoteWizardProgress } from "./QuoteWizardProgress";
import { QuoteWizardStepSource } from "./QuoteWizardStepSource";
import { QuoteWizardStepDelai } from "./QuoteWizardStepDelai";
import { QuoteWizardStepType } from "./QuoteWizardStepType";
import { QuoteWizardStepDescription } from "./QuoteWizardStepDescription";
import { QuoteWizardStepCategory } from "./QuoteWizardStepCategory";
import { QuoteWizardStepBudget } from "./QuoteWizardStepBudget";
import { QuoteWizardStepContact } from "./QuoteWizardStepContact";
import { QuoteWizardStepReview } from "./QuoteWizardStepReview";
import { QuoteWizardSuccess } from "./QuoteWizardSuccess";
import { QuoteWizardIaQualif } from "./QuoteWizardIaQualif";

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
  const locale = useLocale();
  const {
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
  } = useQuoteWizard({ initialName, initialEmail, t, locale, submitQuoteRequest, qualifyQuoteRequest });

  return (
    <Card variant="soft" className="mx-auto max-w-[640px] p-6">
      {typeof current === "number" && (
        <>
          <QuoteWizardProgress current={current} phases={phases} t={t} />

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
            <QuoteWizardStepType
              answers={answers}
              update={update}
              t={t}
              typeDescriptions={typeDescriptions}
              setCategoryKey={setCategoryKey}
            />
          )}

          {current === 2 && (
            <QuoteWizardStepCategory
              answers={answers}
              update={update}
              t={t}
              categoryKey={categoryKey}
              categoryOptionKeys={categoryOptionKeys}
            />
          )}

          {current === 3 && <QuoteWizardStepSource answers={answers} update={update} t={t} />}

          {current === 4 && <QuoteWizardStepDescription answers={answers} update={update} t={t} />}

          {current === 5 && (
            <QuoteWizardStepBudget answers={answers} update={update} t={t} budgetOptions={budgetOptions} />
          )}

          {current === 6 && <QuoteWizardStepDelai answers={answers} update={update} t={t} />}

          {current === 7 && (
            <QuoteWizardStepContact
              answers={answers}
              update={update}
              t={t}
              isEmailChannel={isEmailChannel}
              showError={showError}
            />
          )}

          {current === 8 && (
            <QuoteWizardStepReview
              answers={answers}
              t={t}
              consent={consent}
              setConsent={setConsent}
              setCurrent={setCurrent}
            />
          )}

          {showError && (
            <p role="alert" className="mt-3 text-sm font-medium text-danger">
              {current === 7 && !isEmailChannel && !isValidPhone(answers.phone)
                ? t("errorPhoneRequired")
                : t("errorRequired")}
            </p>
          )}

          <div className="mt-6 flex items-center justify-between">
            <button
              type="button"
              onClick={goBack}
              className={clsx("btn-secondary", current === 1 && "invisible")}
            >
              {t("back")}
            </button>
            <button type="button" onClick={goNext} className="btn-primary" disabled={iaLoading}>
              {iaLoading ? t("iaGenerating") : current === TOTAL_STEPS ? t("continueWithAssistant") : t("next")}
            </button>
          </div>
        </>
      )}

      {current === "ia-qualif" && (
        <QuoteWizardIaQualif
          t={t}
          iaThread={iaThread}
          iaAnswer={iaAnswer}
          setIaAnswer={setIaAnswer}
          iaFinished={iaFinished}
          iaNext={iaNext}
          handleFinalSubmit={handleFinalSubmit}
          submitting={submitting}
          submitError={submitError}
        />
      )}

      {current === "success" && (
        <QuoteWizardSuccess t={t} successHref={successHref} successLabel={successLabel} />
      )}
    </Card>
  );
}
