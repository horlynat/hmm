"use client";

import { useLocale, useTranslations } from "next-intl";
import { submitQuoteRequest, qualifyQuoteRequest } from "@/actions/quote";
import { TOTAL_STEPS } from "@/lib/quoteWizard/constants";
import { isValidPhone } from "@/lib/quoteWizard/validators";
import { useQuoteWizard } from "@/lib/quoteWizard/useQuoteWizard";
import { ProtoIcon } from "@/components/proto/ProtoSvg";
import { QuoteWizardProtoProgress } from "./QuoteWizardProtoProgress";
import { StepType, StepCategory, StepSource, StepDescription, StepBudget, StepDelai, StepContact } from "./QuoteWizardProtoSteps";
import { QuoteWizardProtoReview } from "./QuoteWizardProtoReview";
import { QuoteWizardProtoIaQualif } from "./QuoteWizardProtoIaQualif";
import { QuoteWizardProtoSuccess } from "./QuoteWizardProtoSuccess";

/**
 * Questionnaire guidé de devis (étape « Demander un devis » de /contact) —
 * markup exact du prototype (`.quest .qprog .hp .qq .qerr .qnav`), piloté
 * par la même logique que l'espace compte (`useQuoteWizard`, actions
 * `submitQuoteRequest`/`qualifyQuoteRequest`) : seul l'habillage diffère.
 */
export function QuoteWizardProto({ initialName, initialEmail }: { initialName?: string; initialEmail?: string }) {
  const t = useTranslations("contact.wizard");
  const tc = useTranslations("contactPage");
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

  if (current === "ia-qualif") {
    return (
      <QuoteWizardProtoIaQualif
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
    );
  }

  if (current === "success") {
    return <QuoteWizardProtoSuccess t={t} answers={answers} />;
  }

  return (
    <form
      className="quest"
      noValidate
      onSubmit={(e) => {
        e.preventDefault();
        void goNext();
      }}
    >
      <QuoteWizardProtoProgress current={current} phases={phases} t={t} />

      {/* Champ piège anti-bot, invisible pour un humain (cf. `.hp` du prototype). */}
      <div className="hp" aria-hidden="true">
        <label htmlFor="quote-hp">{tc("honeypotLabel")}</label>
        <input id="quote-hp" name="website" tabIndex={-1} autoComplete="off" value={honeypot} onChange={(e) => setHoneypot(e.target.value)} />
      </div>

      {current === 1 && <StepType answers={answers} update={update} t={t} typeDescriptions={typeDescriptions} setCategoryKey={setCategoryKey} />}
      {current === 2 && <StepCategory answers={answers} update={update} t={t} categoryKey={categoryKey} categoryOptionKeys={categoryOptionKeys} />}
      {current === 3 && <StepSource answers={answers} update={update} t={t} />}
      {current === 4 && <StepDescription answers={answers} update={update} t={t} />}
      {current === 5 && <StepBudget answers={answers} update={update} t={t} budgetOptions={budgetOptions} />}
      {current === 6 && <StepDelai answers={answers} update={update} t={t} />}
      {current === 7 && <StepContact answers={answers} update={update} t={t} isEmailChannel={isEmailChannel} showError={showError} />}
      {current === 8 && <QuoteWizardProtoReview answers={answers} t={t} consent={consent} setConsent={setConsent} setCurrent={setCurrent} />}

      <p className="qerr" id="qerr" role="alert">
        {showError ? (current === 7 && !isEmailChannel && !isValidPhone(answers.phone) ? t("errorPhoneRequired") : t("errorRequired")) : ""}
      </p>

      <div className="qnav">
        {current > 1 ? (
          <button type="button" className="b b-g" onClick={goBack}>
            <ProtoIcon name="al" />
            {t("back")}
          </button>
        ) : (
          <span />
        )}
        <button type="submit" className="b b-p" disabled={iaLoading}>
          {iaLoading ? t("iaGenerating") : current === TOTAL_STEPS ? t("continueWithAssistant") : t("next")}
          {current !== TOTAL_STEPS && <ProtoIcon name="ar" />}
        </button>
      </div>
    </form>
  );
}
