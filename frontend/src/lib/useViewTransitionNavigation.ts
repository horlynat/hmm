"use client";

import type { ComponentProps } from "react";
import { useCallback } from "react";
import { Link, useRouter } from "@/i18n/navigation";

// Le typage `href` de <Link> et celui de useRouter().push() divergent
// légèrement dans next-intl (query optionnelle vs nullable) alors qu'ils
// acceptent les mêmes valeurs en pratique — on type ce hook sur celui de
// <Link> (c'est ce que ViewTransitionLink lui transmet) et on caste au seul
// point d'appel de push() ci-dessous.
type Href = ComponentProps<typeof Link>["href"];

/**
 * Temps max accordé à la navigation avant de capturer l'état "après" quoi
 * qu'il arrive. En-deçà du timeout interne du navigateur pour
 * document.startViewTransition() lui-même (~4s dans Chrome) — sans ce filet,
 * une navigation lente (ou un onglet mis en arrière-plan pendant le clic, qui
 * suspend requestAnimationFrame) laisserait le navigateur avorter la
 * transition avec un TimeoutError non intercepté (constaté en pratique).
 */
const MAX_WAIT_MS = 1500;

/**
 * Résout après `ms`, ou dès que `predicate()` devient vrai — sondage par
 * `setTimeout` (pas `requestAnimationFrame`, qui se met en pause si l'onglet
 * passe en arrière-plan pendant la navigation, ce qui empêcherait aussi bien
 * la détection que l'échéance elle-même de jamais se déclencher).
 */
function waitUntil(predicate: () => boolean, ms: number): Promise<void> {
  return new Promise((resolve) => {
    let settled = false;
    const settle = () => {
      if (settled) return;
      settled = true;
      resolve();
    };
    const deadline = window.setTimeout(settle, ms);
    const interval = window.setInterval(() => {
      if (predicate()) {
        window.clearTimeout(deadline);
        window.clearInterval(interval);
        settle();
      }
    }, 16);
  });
}

/**
 * Vrai dès qu'un élément marqué `.vt-target` porte le `view-transition-name`
 * attendu. Nécessaire en l'absence du composant React `<ViewTransition>`
 * (indisponible sur la version de React stable épinglée par ce projet,
 * 19.2.4 — ce composant n'existe que dans les builds canary ; décision
 * produit : pas d'upgrade de dépendance pour l'instant) : router.push()
 * déclenche un rendu React asynchrone (fetch du payload RSC puis commit)
 * sans promesse exposée pour en connaître la fin — attendre l'apparition
 * réelle de la cible est plus fiable qu'un délai arbitraire.
 */
function hasTarget(name: string): boolean {
  const candidates = document.querySelectorAll<HTMLElement>(".vt-target");
  for (const el of candidates) {
    if (el.style.viewTransitionName === name) return true;
  }
  return false;
}

/**
 * Navigue via l'API navigateur View Transitions (document.startViewTransition)
 * plutôt qu'un <Link> nu — anime la transition entre l'ancienne et la
 * nouvelle page, et fait "morphir" un élément partagé (ex. l'image d'une
 * carte projet vers l'image de couverture de sa page de détail) quand
 * `viewTransitionName` est fourni et correspond à un élément `.vt-target` sur
 * les deux pages. Repli silencieux sur une navigation normale si le
 * navigateur ne supporte pas l'API (Safari/Firefox anciens) ou si
 * `prefers-reduced-motion` est actif (géré côté CSS globals.css, pas ici :
 * la transition tourne quand même mais sans mouvement, cf. le guide View
 * Transitions — c'est l'approche recommandée plutôt que de désactiver l'API
 * elle-même).
 */
export function useViewTransitionNavigate() {
  const router = useRouter();

  return useCallback(
    (href: Href, viewTransitionName?: string) => {
      if (typeof document === "undefined" || !("startViewTransition" in document)) {
        router.push(href as Parameters<typeof router.push>[0]);
        return;
      }

      document.startViewTransition(async () => {
        router.push(href as Parameters<typeof router.push>[0]);
        await waitUntil(() => (viewTransitionName ? hasTarget(viewTransitionName) : true), MAX_WAIT_MS);
      });
    },
    [router],
  );
}
