"use client";

import { useEffect } from "react";
import { usePathname } from "@/i18n/navigation";

/**
 * Animation d'apparition `.rv` / `.rv.in` du prototype — SANS jamais cacher
 * le contenu sans JavaScript : le HTML serveur porte déjà `rv in` (visible).
 * Une fois hydraté, seuls les éléments encore SOUS l'écran perdent `in`
 * (invisibles pour l'utilisateur à ce moment-là), puis le retrouvent à leur
 * entrée dans la fenêtre. Ce qui est déjà affiché ne clignote donc jamais.
 * Animations réduites : rien n'est retiré.
 *
 * Monté une fois dans (marketing)/layout.tsx ; relancé à chaque navigation.
 */
export function ProtoReveal() {
  const pathname = usePathname();

  useEffect(() => {
    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;
    if (!("IntersectionObserver" in window)) return;

    const below = [...document.querySelectorAll<HTMLElement>(".proto-skin .rv.in")].filter(
      (el) => el.getBoundingClientRect().top > window.innerHeight,
    );
    if (below.length === 0) return;

    const observer = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (!entry.isIntersecting) continue;
          entry.target.classList.add("in");
          observer.unobserve(entry.target);
        }
      },
      { rootMargin: "0px 0px -8% 0px" },
    );
    for (const el of below) {
      el.classList.remove("in");
      observer.observe(el);
    }
    return () => {
      observer.disconnect();
      // Rien ne doit rester masqué après démontage.
      for (const el of below) el.classList.add("in");
    };
  }, [pathname]);

  return null;
}
