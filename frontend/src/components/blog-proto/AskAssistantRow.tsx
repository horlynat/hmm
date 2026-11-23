"use client";

import { useTranslations } from "next-intl";
import { whatsappLink } from "@/lib/whatsapp";
import { requestAiAssistantOpen } from "@/lib/aiAssistantOpen";
import { ProtoIcon } from "@/components/proto/ProtoSvg";

/** Rangée « Demander à l'assistant / Écrire sur WhatsApp » du prototype (atog -> assistant réel). */
export function AskAssistantRow({ message }: { message?: string }) {
  const t = useTranslations("blogPage");
  const href = whatsappLink(message ?? t("askMessage"));
  return (
    <div className="row4">
      <button type="button" className="b b-g sm" onClick={requestAiAssistantOpen}>
        <ProtoIcon name="spark" />
        {t("askAssistant")}
      </button>
      <a className="b b-w sm" href={href} target="_blank" rel="noopener noreferrer">
        <ProtoIcon name="wa" />
        {t("askWhatsapp")}
      </a>
    </div>
  );
}
