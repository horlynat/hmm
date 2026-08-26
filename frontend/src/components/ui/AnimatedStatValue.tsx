"use client";

import { useEffect, useMemo, useRef, useState } from "react";
import { useReducedMotion } from "@/lib/useReducedMotion";

interface AnimatedStatValueProps {
  /** Ex. "70% des prises de RDV", "-50%" — seul le nombre en tête est animé, le reste de la chaîne est affiché tel quel. `null`/vide tolérés : la saisie admin (label|valeur) n'impose pas une valeur — cf. AdminProjectController::parsePairs — on affiche alors une chaîne vide plutôt que de planter le rendu (et le build statique). */
  value: string | null | undefined;
  className?: string;
}

const NUMBER_PREFIX = /^(-?)(\d+(?:[.,]\d+)?)/;

/**
 * Fait compter de 0 jusqu'au nombre en tête de `value` quand la carte entre
 * dans le viewport (une seule fois), au lieu de l'afficher figé — pour les
 * chiffres d'impact d'un projet (section "Résultats"). `value` reste du
 * texte libre saisi côté admin (ex. "70% des prises de RDV", pas juste
 * "70%") : seul le préfixe numérique est extrait et animé, le reste de la
 * chaîne (signe compris) est conservé tel quel autour.
 */
export function AnimatedStatValue({ value, className }: AnimatedStatValueProps) {
  const safeValue = value ?? "";
  const match = useMemo(() => safeValue.match(NUMBER_PREFIX), [safeValue]);
  const ref = useRef<HTMLSpanElement>(null);
  const [display, setDisplay] = useState(() => (match ? "0" : safeValue));
  const reduce = useReducedMotion();

  useEffect(() => {
    if (!match) return;

    const target = parseFloat(match[2].replace(",", "."));
    const decimals = /[.,]/.test(match[2]) ? 1 : 0;

    if (reduce) {
      // Différé au frame suivant plutôt qu'un `setState` synchrone dans
      // l'effet (cascading render) : cf. règle react-hooks/set-state-in-effect
      // — même traitement que Reveal.tsx pour le cas prefers-reduced-motion.
      const raf = requestAnimationFrame(() => setDisplay(target.toFixed(decimals)));
      return () => cancelAnimationFrame(raf);
    }

    const el = ref.current;
    if (!el) return;

    const observer = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (!entry.isIntersecting) continue;
          const duration = 1100;
          const start = performance.now();
          const tick = (now: number) => {
            const progress = Math.min(1, (now - start) / duration);
            const eased = 1 - (1 - progress) ** 3;
            setDisplay((target * eased).toFixed(decimals));
            if (progress < 1) requestAnimationFrame(tick);
          };
          requestAnimationFrame(tick);
          observer.disconnect();
        }
      },
      { rootMargin: "-80px" },
    );
    observer.observe(el);
    return () => observer.disconnect();
  }, [match, reduce]);

  if (!match) {
    return <span className={className}>{safeValue}</span>;
  }

  const [, sign] = match;
  const suffix = safeValue.slice(match[0].length);

  return (
    <span ref={ref} className={className}>
      {sign}
      {display}
      {suffix}
    </span>
  );
}
