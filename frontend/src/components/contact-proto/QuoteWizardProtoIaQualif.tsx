import type { IaThreadMessage, WizardT } from "@/lib/quoteWizard/types";
import { ProtoIcon } from "@/components/proto/ProtoSvg";

/**
 * Qualification IA (1 à 2 questions générées par Claude Sonnet avant
 * l'envoi, cf. useQuoteWizard::fetchIaQuestions) — réutilise les classes de
 * dialogue du prototype déjà employées par l'assistant du site (`.log
 * .arow .bub .bu .bb .dlogo .typing`), cohérent avec le reste du site.
 */
export function QuoteWizardProtoIaQualif({
  t,
  iaThread,
  iaAnswer,
  setIaAnswer,
  iaFinished,
  iaNext,
  handleFinalSubmit,
  submitting,
  submitError,
}: {
  t: WizardT;
  iaThread: IaThreadMessage[];
  iaAnswer: string;
  setIaAnswer: (value: string) => void;
  iaFinished: boolean;
  iaNext: (skip: boolean) => void;
  handleFinalSubmit: () => void;
  submitting: boolean;
  submitError: boolean;
}) {
  return (
    <div>
      <span className="kick">{t("iaLabel")}</span>
      <h3 className="qq" tabIndex={-1}>
        {t("iaQuestion")}
      </h3>
      <div className="log" role="log" aria-live="polite">
        {iaThread.map((m, i) =>
          m.who === "user" ? (
            <div key={i} className="bub bu">
              {m.text}
            </div>
          ) : (
            <div key={i} className="arow">
              <span className="dlogo sm">
                <ProtoIcon name="spark" />
              </span>
              <div className="bub bb">{m.text}</div>
            </div>
          ),
        )}
      </div>
      {!iaFinished && (
        <>
          <label className="sr" htmlFor="quote-ia-answer">
            {t("iaAnswerPlaceholder")}
          </label>
          <textarea
            id="quote-ia-answer"
            className="fi"
            style={{ minHeight: "70px", marginTop: "14px" }}
            placeholder={t("iaAnswerPlaceholder")}
            value={iaAnswer}
            onChange={(e) => setIaAnswer(e.target.value)}
          />
          <div style={{ display: "flex", justifyContent: "flex-end", gap: "12px", marginTop: "14px" }}>
            <button type="button" className="b b-g" onClick={() => iaNext(true)}>
              {t("iaSkip")}
            </button>
            <button type="button" className="b b-p" onClick={() => iaNext(false)}>
              {t("iaAnswer")}
            </button>
          </div>
        </>
      )}
      {iaFinished && (
        <div style={{ display: "flex", justifyContent: "flex-end", marginTop: "16px" }}>
          <button type="button" className="b b-p" disabled={submitting} onClick={handleFinalSubmit}>
            {submitting ? t("iaAnswer") : t("sendRequest")}
          </button>
        </div>
      )}
      {submitError && (
        <p className="qerr" role="alert" style={{ marginTop: "14px" }}>
          {t("errorSubmit")}
        </p>
      )}
    </div>
  );
}
