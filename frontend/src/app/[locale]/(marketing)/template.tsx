"use client";

import { useEffect, useState, type ReactNode } from "react";
import { useReducedMotion } from "@/lib/useReducedMotion";

/**
 * Transition d'entrée à chaque navigation (fade léger). Un `template.tsx` est
 * remonté à chaque changement de route, contrairement au `layout.tsx`.
 * Neutralisé si l'utilisateur préfère des animations réduites.
 *
 * CSS pur (pas de `motion`) : évite de charger cette lib sur chaque page pour
 * un simple fondu.
 */
export default function LocaleTemplate({ children }: { children: ReactNode }) {
  const [visible, setVisible] = useState(false);
  const reduce = useReducedMotion();

  useEffect(() => {
    if (reduce) return;
    // Différé au frame suivant plutôt qu'un `setState` synchrone dans
    // l'effet (cascading render) : cf. règle react-hooks/set-state-in-effect.
    const raf = requestAnimationFrame(() => setVisible(true));
    return () => cancelAnimationFrame(raf);
  }, [reduce]);

  if (reduce) return <>{children}</>;

  return (
    <div style={{ opacity: visible ? 1 : 0, transition: "opacity 0.3s ease-out" }}>
      {children}
    </div>
  );
}
