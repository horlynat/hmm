import type { ReactNode } from "react";
import { Header, Footer, ScrollTopButton, WhatsappFab } from "@/components/layout";
import { AiAssistantWidgetLazy as AiAssistantWidget } from "@/components/ai-assistant";
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

  // Refonte 2026-11 : site vitrine en thème SOMBRE UNIQUEMENT (décision de
  // l'utilisateur), quel que soit le choix enregistré ou la préférence
  // système — l'espace compte (/compte, layout distinct) garde son bouton de
  // thème. La classe `dark` posée ici fait basculer les variables CSS (.dark
  // dans globals.css) et les variantes `dark:` pour tout le sous-arbre ; le
  // conteneur peint lui-même son fond (bg-bg-default) car <body>, au-dessus,
  // resterait clair si la préférence enregistrée est « clair ».
  return (
    <div className="dark flex min-h-full flex-1 flex-col bg-bg-default text-brand-dark" style={{ colorScheme: "dark" }}>
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
    </div>
  );
}
