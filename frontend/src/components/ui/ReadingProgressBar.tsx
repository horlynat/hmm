"use client";

import { useEffect, useState } from "react";

/**
 * Fin liseré en haut de page, largeur proportionnelle à la progression de
 * lecture (scroll vertical) — donne un sentiment de "trajet" avec un début
 * et une fin sur les pages de détail longues (article, projet), plutôt qu'un
 * défilement sans repère. `position: fixed`, ne prend aucune place dans le
 * flux — safe à poser en tête de n'importe quelle page.
 */
export function ReadingProgressBar() {
  const [progress, setProgress] = useState(0);

  useEffect(() => {
    function onScroll() {
      const { scrollTop, scrollHeight, clientHeight } = document.documentElement;
      const max = scrollHeight - clientHeight;
      setProgress(max > 0 ? Math.min(1, Math.max(0, scrollTop / max)) : 0);
    }
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
    window.addEventListener("resize", onScroll);
    return () => {
      window.removeEventListener("scroll", onScroll);
      window.removeEventListener("resize", onScroll);
    };
  }, []);

  return (
    <div
      aria-hidden="true"
      // z-[60] : au-dessus du header (sticky, z-50) — sinon la fine barre se
      // retrouve masquée derrière son fond opaque au lieu de rester visible
      // en toutes circonstances pendant le défilement.
      className="pointer-events-none fixed inset-x-0 top-0 z-[60] h-[3px] bg-transparent"
    >
      <div
        className="h-full"
        style={{
          width: `${progress * 100}%`,
          background: "linear-gradient(90deg, var(--cta-gradient-from), var(--cta-gradient-to))",
          transition: "width 0.1s linear",
        }}
      />
    </div>
  );
}
