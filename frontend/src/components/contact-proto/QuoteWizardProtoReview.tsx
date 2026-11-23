import { Link } from "@/i18n/navigation";
import type { WizardT } from "@/lib/quoteWizard/types";
import type { QuoteWizardAnswers } from "@/lib/types";

/**
 * Étape 8 (récapitulatif + consentement) — markup dérivé du vocabulaire du
 * prototype (`.qq`, listes `.chk`, `.ck`) : le prototype n'a pas prévu cette
 * étape dans sa maquette statique (son questionnaire simplifié n'a pas de
 * récapitulatif dédié), donc le style est extrapolé de ses propres classes
 * plutôt qu'inventé. Logique identique à `QuoteWizardStepReview`.
 */
export function QuoteWizardProtoReview({
  answers,
  t,
  consent,
  setConsent,
  setCurrent,
}: {
  answers: QuoteWizardAnswers;
  t: WizardT;
  consent: boolean;
  setConsent: (value: boolean) => void;
  setCurrent: (step: number) => void;
}) {
  const sections = [
    {
      title: t("recapSectionProject"),
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
      title: t("recapSectionBudget"),
      step: 5,
      items: [
        [t("recapBudget"), answers.budget],
        [t("recapDelai"), answers.delai],
      ],
    },
    {
      title: t("recapSectionContact"),
      step: 7,
      items: [
        [t("recapName"), answers.name],
        [t("recapEmail"), answers.email],
        [t("recapPhone"), answers.phone || t("recapEmpty")],
        [t("recapCanal"), answers.canal],
      ],
    },
  ];

  return (
    <div>
      <h3 className="qq" tabIndex={-1}>
        {t("step8Question")}
      </h3>
      <div style={{ display: "flex", flexDirection: "column", gap: "14px", marginBottom: "20px" }}>
        {sections.map((section) => (
          <div key={section.title} style={{ border: "1px solid var(--ln)", borderRadius: "14px", padding: "16px 18px" }}>
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", gap: "12px", marginBottom: "8px" }}>
              <span className="kick" style={{ margin: 0 }}>
                {section.title}
              </span>
              <button type="button" className="linkbtn" onClick={() => setCurrent(section.step)}>
                {t("editStep")}
              </button>
            </div>
            <ul style={{ listStyle: "none", margin: 0, padding: 0 }}>
              {section.items.map(([label, value]) => (
                <li
                  key={label}
                  style={{
                    display: "flex",
                    justifyContent: "space-between",
                    gap: "16px",
                    padding: "8px 0",
                    fontSize: "14.5px",
                    borderTop: "1px solid var(--ln)",
                  }}
                >
                  <span className="mut">{label}</span>
                  <span style={{ textAlign: "right", fontWeight: 600, maxWidth: "60%" }}>{value || t("recapEmpty")}</span>
                </li>
              ))}
            </ul>
          </div>
        ))}
      </div>
      <p className="asec">{t("trustResponseTime")}</p>
      <p className="dm" style={{ fontSize: "14px", margin: "10px 0" }}>
        {t("reassurance1")}
      </p>
      <p className="dm" style={{ fontSize: "14px", marginBottom: "16px" }}>
        {t("reassurance2")}
      </p>
      <label className="ck">
        <input type="checkbox" checked={consent} onChange={(e) => setConsent(e.target.checked)} />
        <span>
          {t.rich("consent", {
            privacy: (chunks) => (
              <Link className="lnk" href="/politique-de-confidentialite">
                {chunks}
              </Link>
            ),
          })}
        </span>
      </label>
    </div>
  );
}
