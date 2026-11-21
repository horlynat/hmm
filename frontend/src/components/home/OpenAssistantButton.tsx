"use client";

import type { ReactNode } from "react";
import { requestAiAssistantOpen } from "@/lib/aiAssistantOpen";

/**
 * Ouvre l'assistant IA du site (AiAssistantWidget, monté une fois dans
 * MarketingLayout). Ne l'afficher que si le widget est réellement monté
 * (réglages IA disponibles) — sinon ce serait un bouton sans effet.
 */
export function OpenAssistantButton({ children, className }: { children: ReactNode; className?: string }) {
  return (
    <button type="button" className={className} onClick={requestAiAssistantOpen}>
      {children}
    </button>
  );
}
