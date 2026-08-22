import { LegalLink } from "@/components/ui";
import type { WizardT } from "@/lib/quoteWizard/types";
import type { QuoteWizardAnswers } from "@/lib/types";

interface QuoteWizardStepReviewProps {
  answers: QuoteWizardAnswers;
  t: WizardT;
  consent: boolean;
  setConsent: (value: boolean) => void;
  setCurrent: (step: number) => void;
}

export function QuoteWizardStepReview({ answers, t, consent, setConsent, setCurrent }: QuoteWizardStepReviewProps) {
  return (
    <div>
      <div className="mb-4 text-lg font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
        {t("step8Question")}
      </div>
      <div className="mb-5 space-y-3">
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
            className="rounded-[var(--radius-md)] border border-[var(--border-softer)] p-3.5"
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
            privacy: (chunks) => <LegalLink href="/politique-de-confidentialite">{chunks}</LegalLink>,
          })}
        </span>
      </label>
    </div>
  );
}
