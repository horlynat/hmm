"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import type { Offer } from "@/lib/types";
import { useTabKeyboard } from "@/lib/useTabKeyboard";
import { QuoteWizardProto } from "./QuoteWizardProto";
import { QuickMessageForm } from "./QuickMessageForm";

type Mode = "quest" | "direct";
const MODES: Mode[] = ["quest", "direct"];

/**
 * Bascule « Demander un devis / Message rapide » (`.seg` du prototype),
 * au-dessus du panneau actif (`.cpane#cpane`). Comme le prototype (`ctab`),
 * le focus reste sur l'onglet à l'activation.
 */
export function ContactSegments({
  initialName,
  initialEmail,
  offers,
}: {
  initialName?: string;
  initialEmail?: string;
  offers: Offer[];
}) {
  const t = useTranslations("contactPage");
  const [mode, setMode] = useState<Mode>("quest");
  const { tabProps } = useTabKeyboard(MODES, mode);

  return (
    <>
      <div className="seg" role="tablist" aria-label={t("segLabel")}>
        <button type="button" role="tab" id="ctab-quest" aria-controls="cpane" {...tabProps("quest")} onClick={() => setMode("quest")}>
          <b>{t("segQuestTitle")}</b>
          <small>{t("segQuestSub")}</small>
        </button>
        <button type="button" role="tab" id="ctab-direct" aria-controls="cpane" {...tabProps("direct")} onClick={() => setMode("direct")}>
          <b>{t("segDirectTitle")}</b>
          <small>{t("segDirectSub")}</small>
        </button>
      </div>
      <div className="cpane" id="cpane" role="tabpanel" aria-labelledby={`ctab-${mode}`}>
        {mode === "quest" ? <QuoteWizardProto initialName={initialName} initialEmail={initialEmail} /> : <QuickMessageForm offers={offers} />}
      </div>
    </>
  );
}
