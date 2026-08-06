import type { ReactNode } from "react";
import { Header, Footer, ScrollTopButton, WhatsappFab } from "@/components/layout";
import { AiAssistantWidget } from "@/components/ai-assistant";
import { getAiAssistantEntries, getAiAssistantSettings } from "@/lib/api/ai-assistant";

/**
 * Chrome du site vitrine (Header/Footer/widgets), imbriqué sous le layout
 * racine `[locale]/layout.tsx` — `/compte` n'en hérite pas (cf. plan de
 * refonte de l'espace compte : header dédié plutôt que celui du site).
 */
export default async function MarketingLayout({
  children,
  params,
}: {
  children: ReactNode;
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const [aiAssistantSettings, aiAssistantEntries] = await Promise.all([
    getAiAssistantSettings(locale),
    getAiAssistantEntries(locale),
  ]);

  return (
    <>
      <Header />
      <main id="main-content" className="flex-1">
        {children}
      </main>
      <Footer locale={locale} />
      <ScrollTopButton />
      <WhatsappFab locale={locale} />
      {/* Widget décoratif, présent sur toutes les pages vitrine : une panne API
          le masque simplement plutôt que de faire échouer le rendu. */}
      {aiAssistantSettings && (
        <AiAssistantWidget settings={aiAssistantSettings} entries={aiAssistantEntries} />
      )}
    </>
  );
}
