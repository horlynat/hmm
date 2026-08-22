import type { ComponentProps } from "react";
import { ButtonLink } from "@/components/ui";
import type { Link } from "@/i18n/navigation";
import type { WizardT } from "@/lib/quoteWizard/types";

interface QuoteWizardSuccessProps {
  t: WizardT;
  successHref?: ComponentProps<typeof Link>["href"];
  successLabel?: string;
}

export function QuoteWizardSuccess({ t, successHref, successLabel }: QuoteWizardSuccessProps) {
  return (
    <div role="status" className="py-4 text-center">
      <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-success/10 text-2xl text-success">
        ✓
      </div>
      <h3 className="mb-2 text-lg font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
        {t("successTitle")}
      </h3>
      <p className="mb-5 text-sm opacity-70">{t("successText")}</p>
      <div className="mx-auto max-w-[420px] rounded-[var(--radius-md)] bg-bg-default p-4 text-left">
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
  );
}
