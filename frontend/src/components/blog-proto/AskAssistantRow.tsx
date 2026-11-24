"use client";

import { useTranslations } from "next-intl";
import { whatsappLink } from "@/lib/whatsapp";
import { requestAiAssistantOpen } from "@/lib/aiAssistantOpen";
import { ProtoIcon } from "@/components/proto/ProtoSvg";

/**
 * Boutons « Demander à l'assistant / Écrire sur WhatsApp » du prototype
 * (atog -> assistant réel). Liste du blog : rangée `.row4`, assistant d'abord ;
 * colonne d'un article (`.aq`, variante `aside`) : WhatsApp d'abord, sans
 * enveloppe, comme le prototype.
 */
export function AskAssistantRow({ message, variant }: { message?: string; variant?: "aside" }) {
  const t = useTranslations("blogPage");
  const href = whatsappLink(message ?? t("askMessage"));
  const assistant = (
    <button type="button" className="b b-g sm" onClick={requestAiAssistantOpen}>
      <ProtoIcon name="spark" />
      {t("askAssistant")}
    </button>
  );
  const whatsapp = (
    <a className="b b-w sm" href={href} target="_blank" rel="noopener noreferrer">
      <ProtoIcon name="wa" size={variant === "aside" ? 16 : undefined} />
      {t("askWhatsapp")}
    </a>
  );
  if (variant === "aside") {
    return (
      <>
        {whatsapp}
        {assistant}
      </>
    );
  }
  return (
    <div className="row4">
      {assistant}
      {whatsapp}
    </div>
  );
}
