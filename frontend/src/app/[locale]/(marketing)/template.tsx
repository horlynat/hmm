import type { ReactNode } from "react";

/**
 * Transition d'entrée à chaque navigation (fondu léger). Un `template.tsx` est
 * remonté à chaque changement de route, contrairement au `layout.tsx` : le
 * nouvel élément rejoue donc l'animation.
 *
 * Animation CSS pure (`.page-fade-in`, globals.css), et non un état React
 * basculé après hydratation : l'ancienne version rendait toute la page à
 * `opacity: 0` dans le HTML serveur jusqu'à l'exécution du JavaScript —
 * page noire plusieurs secondes sur mobile lent, invisible si le JS échoue.
 * Les animations réduites sont respectées par la règle globale
 * prefers-reduced-motion.
 */
export default function MarketingTemplate({ children }: { children: ReactNode }) {
  return <div className="page-fade-in">{children}</div>;
}
