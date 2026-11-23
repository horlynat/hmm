import type { ReactNode } from "react";
import { Header } from "@/components/layout";
import { SiteFooter } from "@/components/proto/SiteFooter";
import { SiteBand } from "@/components/proto/SiteBand";
import { SiteAssistant } from "@/components/proto/SiteAssistant";
import { getAiAssistantSettings } from "@/lib/api/ai-assistant";
import "../../proto-skin.css";

/**
 * Site vitrine — refonte sur le modèle du prototype validé
 * (« Horlynat | Portail Digital – refonte »), structure du layout comprise :
 * en-tête, <main>, bandeau freelance (sauf Connexion et Contact), pied de
 * page, boutons flottants et assistant IA réel.
 *
 * `.proto-skin` porte la feuille de style du prototype mise sous espace de
 * noms (proto-skin.css) ; thème sombre seul (décision de l'utilisateur),
 * l'espace compte (/compte, layout distinct) n'est pas concerné.
 */
export default async function MarketingLayout({
  children,
  params,
}: {
  children: ReactNode;
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  // Message de repli de l'assistant (back-office) ; une panne API ne bloque
  // pas le rendu : le panneau retombe alors sur le renvoi vers WhatsApp.
  const aiSettings = await getAiAssistantSettings(locale);

  return (
    <div className="proto-skin dark flex min-h-full flex-1 flex-col" style={{ colorScheme: "dark" }}>
      <Header />
      {/* id attendu par le lien d'évitement du layout racine (#main-content). */}
      <main id="main-content" className="flex-1">
        {children}
      </main>
      <SiteBand />
      <SiteFooter locale={locale} />
      <SiteAssistant fallback={aiSettings?.fallback ?? null} />
    </div>
  );
}
