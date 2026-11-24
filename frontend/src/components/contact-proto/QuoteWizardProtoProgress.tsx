import { phaseIndexForStep, TOTAL_STEPS } from "@/lib/quoteWizard/constants";
import type { WizardT } from "@/lib/quoteWizard/types";

/**
 * Fil d'ariane + barre de progression du questionnaire (`.qprog .qbar`),
 * markup du prototype. Équivalent de `QuoteWizardProgress`.
 */
export function QuoteWizardProtoProgress({
  current,
  phases,
  t,
}: {
  current: number;
  phases: { key: string; label: string }[];
  t: WizardT;
}) {
  const activeIndex = phaseIndexForStep(current);

  return (
    <div className="qprog">
      <div
        className="qbar"
        role="progressbar"
        aria-label={t("progressLabel")}
        aria-valuemin={1}
        aria-valuemax={TOTAL_STEPS}
        aria-valuenow={current}
        aria-valuetext={t(`step${current}Label` as `step${1 | 2 | 3 | 4 | 5 | 6 | 7 | 8}Label`)}
      >
        <i style={{ width: `${(current / TOTAL_STEPS) * 100}%` }} />
      </div>
      <ol>
        {phases.map((phase, i) => (
          <li key={phase.key} className={i === activeIndex ? "on" : i < activeIndex ? "done" : ""}>
            {phase.label}
          </li>
        ))}
      </ol>
      <p aria-live="polite">{t(`step${current}Label` as `step${1 | 2 | 3 | 4 | 5 | 6 | 7 | 8}Label`)}</p>
    </div>
  );
}
