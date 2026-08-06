"use client";

import { useEffect, useRef, useState, type ReactNode } from "react";
import { useReducedMotion } from "@/lib/useReducedMotion";

interface RevealProps {
  children: ReactNode;
  /** Délai avant l'apparition (s) — utile pour un effet en cascade. */
  delay?: number;
  /** Décalage vertical initial (px). */
  y?: number;
  className?: string;
  as?: "div" | "section" | "li" | "article";
}

/**
 * Un seul `IntersectionObserver` partagé entre toutes les instances de
 * `Reveal` (plutôt qu'un par carte) — évite le coût de la lib `motion` côté
 * client pour une simple animation d'apparition au scroll.
 */
let sharedObserver: IntersectionObserver | null = null;
const onIntersect = new Map<Element, () => void>();

function getSharedObserver() {
  if (sharedObserver) return sharedObserver;
  sharedObserver = new IntersectionObserver(
    (entries) => {
      for (const entry of entries) {
        if (!entry.isIntersecting) continue;
        onIntersect.get(entry.target)?.();
        sharedObserver?.unobserve(entry.target);
        onIntersect.delete(entry.target);
      }
    },
    { rootMargin: "-80px" },
  );
  return sharedObserver;
}

/**
 * Apparition douce au défilement (fade + translation), déclenchée une seule
 * fois à l'entrée dans le viewport. Respecte `prefers-reduced-motion` : si
 * l'utilisateur a réduit les animations, le contenu s'affiche immédiatement
 * sans transformation.
 */
export function Reveal({ children, delay = 0, y = 16, className, as = "div" }: RevealProps) {
  const ref = useRef<HTMLElement>(null);
  const [visible, setVisible] = useState(false);
  const reduce = useReducedMotion();

  useEffect(() => {
    if (reduce) {
      // Différé au frame suivant plutôt qu'un `setState` synchrone dans
      // l'effet (cascading render) : cf. règle react-hooks/set-state-in-effect.
      const raf = requestAnimationFrame(() => setVisible(true));
      return () => cancelAnimationFrame(raf);
    }

    const el = ref.current;
    if (!el) return;
    const observer = getSharedObserver();
    onIntersect.set(el, () => setVisible(true));
    observer.observe(el);
    return () => {
      observer.unobserve(el);
      onIntersect.delete(el);
    };
  }, [reduce]);

  const Tag = as;

  return (
    <Tag
      // Les 4 balises possibles (`as`) sont toutes des HTMLElement standards.
      ref={ref as React.Ref<never>}
      className={className}
      style={{
        opacity: visible ? 1 : 0,
        transform: visible ? "translateY(0)" : `translateY(${y}px)`,
        transition: reduce
          ? "none"
          : `opacity 0.5s ${delay}s cubic-bezier(0.22, 1, 0.36, 1), transform 0.5s ${delay}s cubic-bezier(0.22, 1, 0.36, 1)`,
      }}
    >
      {children}
    </Tag>
  );
}
