"use client";

import dynamic from "next/dynamic";

/**
 * `next/dynamic` avec `ssr: false` n'est autorisé que depuis un Client
 * Component — ce wrapper existe uniquement pour ça, afin que
 * `(marketing)/layout.tsx` (Server Component) puisse charger le widget en
 * lazy sans l'hydrater/l'expédier sur chaque page vitrine avant interaction.
 */
export const AiAssistantWidgetLazy = dynamic(
  () => import("./AiAssistantWidget").then((m) => m.AiAssistantWidget),
  { ssr: false },
);
