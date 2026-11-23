import { getTranslations } from "next-intl/server";
import { siteConfig } from "@/config/site";

/**
 * Bouton WhatsApp flottant — style `.fab` du prototype de refonte
 * (cf. src/app/proto-skin.css), placé au-dessus du bouton de l'assistant IA
 * (AiAssistantWidget, 56 px en bas à droite) avec le même écart que la pile
 * `.fabs` du prototype (12 px).
 */
export async function WhatsappFab({ locale }: { locale: string }) {
  const t = await getTranslations({ locale, namespace: "common" });

  return (
    <a
      className="fab"
      href={siteConfig.whatsappUrl}
      target="_blank"
      rel="noopener noreferrer"
      aria-label={t("whatsapp")}
      style={{ position: "fixed", right: 24, bottom: 92, zIndex: 40, background: "#25D366", color: "#052611" }}
    >
      <svg aria-hidden="true" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <path d="M21 11.5a8.4 8.4 0 0 1-12.2 7.5L3 21l1.9-5.7A8.4 8.4 0 1 1 21 11.5z" />
      </svg>
    </a>
  );
}
