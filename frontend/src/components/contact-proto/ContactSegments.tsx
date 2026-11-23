"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import type { Offer } from "@/lib/types";
import { QuoteWizardProto } from "./QuoteWizardProto";
import { QuickMessageForm } from "./QuickMessageForm";

/**
 * Bascule « Demander un devis / Message rapide » (`.seg` du prototype),
 * au-dessus du panneau actif (`.cpane`).
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
  const [mode, setMode] = useState<"quest" | "direct">("quest");

  return (
    <>
      <div className="seg" role="tablist" aria-label={t("segQuestTitle")}>
        <button type="button" role="tab" aria-selected={mode === "quest"} onClick={() => setMode("quest")}>
          <b>{t("segQuestTitle")}</b>
          <small>{t("segQuestSub")}</small>
        </button>
        <button type="button" role="tab" aria-selected={mode === "direct"} onClick={() => setMode("direct")}>
          <b>{t("segDirectTitle")}</b>
          <small>{t("segDirectSub")}</small>
        </button>
      </div>
      <div className="cpane">{mode === "quest" ? <QuoteWizardProto initialName={initialName} initialEmail={initialEmail} /> : <QuickMessageForm offers={offers} />}</div>
    </>
  );
}
