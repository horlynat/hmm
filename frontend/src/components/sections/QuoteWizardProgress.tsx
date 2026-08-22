import clsx from "clsx";
import { TOTAL_STEPS, phaseIndexForStep } from "@/lib/quoteWizard/constants";
import type { WizardT } from "@/lib/quoteWizard/types";

interface QuoteWizardProgressProps {
  current: number;
  phases: { key: string; label: string }[];
  t: WizardT;
}

export function QuoteWizardProgress({ current, phases, t }: QuoteWizardProgressProps) {
  return (
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
      <div aria-live="polite" className="mb-5 flex flex-wrap items-center justify-between gap-x-3 gap-y-1.5">
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
    </>
  );
}
