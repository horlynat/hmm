import { whatsappLink } from "@/lib/whatsapp";
import type { WizardT } from "@/lib/quoteWizard/types";
import type { QuoteWizardAnswers } from "@/lib/types";
import { ProtoIcon } from "@/components/proto/ProtoSvg";

/**
 * Écran de succès — markup EXACT du prototype (`Q.done` dans quest() :
 * `.qdone .okb .tl`), seul état du questionnaire que la maquette statique
 * détaille entièrement.
 */
export function QuoteWizardProtoSuccess({ t, answers }: { t: WizardT; answers: QuoteWizardAnswers }) {
  return (
    <div className="qdone" role="status">
      <span className="okb">
        <ProtoIcon name="ok" size={22} />
      </span>
      <h3 tabIndex={-1}>{t("successTitle")}</h3>
      <p className="mut">{t("successText")}</p>
      <div className="tl" style={{ margin: "24px 0" }}>
        {[t("successStep1"), t("successStep2"), t("successStep3")].map((step) => (
          <div key={step}>
            <b>{step}</b>
          </div>
        ))}
      </div>
      <div style={{ display: "flex", gap: "12px", flexWrap: "wrap" }}>
        <a className="b b-w" href={whatsappLink(t("successWhatsappMessage", { type: answers.type }))} target="_blank" rel="noopener noreferrer">
          <ProtoIcon name="wa" />
          {t("successWhatsapp")}
        </a>
      </div>
    </div>
  );
}
