import type { ReactNode } from "react";
import { Header } from "@/components/layout";
import { ProtoFooter } from "@/components/proto/ProtoFooter";
import { ProtoBandGate } from "@/components/proto/ProtoBandGate";
import { ProtoFabs } from "@/components/proto/ProtoFabs";
import "../../proto-skin.css";

/**
 * Site vitrine — refonte, PHASE 1 : copie conforme du prototype validé
 * (« Horlynat | Portail Digital – refonte »), structure du layout comprise :
 * en-tête, <main>, bandeau freelance (sauf Connexion et Contact), pied de
 * page, boutons flottants et assistant.
 *
 * `.proto-skin` porte la feuille de style du prototype mise sous espace de
 * noms (proto-skin.css) ; thème sombre seul (décision de l'utilisateur),
 * l'espace compte (/compte, layout distinct) n'est pas concerné.
 *
 * La phase 2 rebranche les données du back-office (offres, contenus,
 * assistant IA réel) page par page.
 */
export default function MarketingLayout({ children }: { children: ReactNode }) {
  return (
    <div className="proto-skin dark flex min-h-full flex-1 flex-col" style={{ colorScheme: "dark" }}>
      <Header />
      {/* id attendu par le lien d'évitement du layout racine (#main-content). */}
      <main id="main-content" className="flex-1">
        {children}
      </main>
      <ProtoBandGate />
      <ProtoFooter />
      <ProtoFabs />
    </div>
  );
}
