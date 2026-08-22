import clsx from "clsx";
import type { IaThreadMessage, WizardT } from "@/lib/quoteWizard/types";

interface QuoteWizardIaQualifProps {
  t: WizardT;
  iaThread: IaThreadMessage[];
  iaAnswer: string;
  setIaAnswer: (value: string) => void;
  iaFinished: boolean;
  iaNext: (skip: boolean) => void;
  handleFinalSubmit: () => void;
  submitting: boolean;
  submitError: boolean;
}

export function QuoteWizardIaQualif({
  t,
  iaThread,
  iaAnswer,
  setIaAnswer,
  iaFinished,
  iaNext,
  handleFinalSubmit,
  submitting,
  submitError,
}: QuoteWizardIaQualifProps) {
  return (
    <div>
      <div className="mb-1 font-mono text-xs uppercase tracking-wide text-brand-primary">{t("iaLabel")}</div>
      <div className="mb-4 text-lg font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
        {t("iaQuestion")}
      </div>
      <div
        role="log"
        aria-live="polite"
        aria-atomic="false"
        className="mb-4 flex max-h-[280px] flex-col gap-2.5 overflow-y-auto"
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
            className="input mb-3 min-h-[70px]"
            placeholder={t("iaAnswerPlaceholder")}
            value={iaAnswer}
            onChange={(e) => setIaAnswer(e.target.value)}
          />
          <div className="flex justify-end gap-3">
            <button type="button" className="btn-secondary" onClick={() => iaNext(true)}>
              {t("iaSkip")}
            </button>
            <button type="button" className="btn-primary" onClick={() => iaNext(false)}>
              {t("iaAnswer")}
            </button>
          </div>
        </>
      )}
      {iaFinished && (
        <div className="flex justify-end">
          <button type="button" className="btn-primary" disabled={submitting} onClick={handleFinalSubmit}>
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
  );
}
