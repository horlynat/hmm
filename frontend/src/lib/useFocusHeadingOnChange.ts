import { useCallback, useEffect, useRef } from "react";
import { useReducedMotion } from "./useReducedMotion";

/**
 * Titres seulement, jamais « tout élément tabIndex=-1 » : les champs pièges
 * anti-robots (`.hp`) portent aussi tabIndex=-1 et précèdent parfois le titre
 * dans le DOM (questionnaire de devis). Les cibler y enverrait le focus — un
 * visiteur qui tape alors remplit le piège et sa demande est ignorée en
 * silence comme celle d'un robot.
 */
const HEADING_SELECTOR = "h1[tabindex='-1'], h2[tabindex='-1'], h3[tabindex='-1']";

/**
 * Déplace le focus clavier vers le titre de l'écran qui vient d'apparaître
 * (premier titre `tabIndex={-1}` du conteneur référencé) à
 * chaque changement de `screenKey` — étape d'un questionnaire, bascule
 * connexion/inscription, écran de succès… Reprend `qRender(focus)` /
 * `aRender()` du prototype : sans ça, un lecteur d'écran ou un clavier reste
 * sur le bouton (parfois démonté) de l'écran précédent et ne découvre le
 * nouveau titre qu'en tabulant à l'aveugle. Ne vole jamais le focus au
 * premier rendu (seulement sur un VRAI changement), et fait défiler le
 * conteneur à l'écran s'il n'y est pas déjà.
 *
 * Renvoie une ref de rappel (et non un objet ref) : elle s'attache à
 * n'importe quel élément (`<form>` du formulaire, `<div>` de l'écran de
 * succès…) — un même composant alterne souvent entre deux racines
 * différentes, une seule étant montée à la fois.
 */
export function useFocusHeadingOnChange(screenKey: unknown): (node: HTMLElement | null) => void {
  const node = useRef<HTMLElement | null>(null);
  const previous = useRef(screenKey);
  const reduceMotion = useReducedMotion();
  const setNode = useCallback((el: HTMLElement | null) => {
    node.current = el;
  }, []);

  useEffect(() => {
    if (previous.current === screenKey) return;
    previous.current = screenKey;

    const container = node.current;
    if (!container) return;

    const heading = container.querySelector<HTMLElement>(HEADING_SELECTOR);
    heading?.focus({ preventScroll: true });

    const rect = container.getBoundingClientRect();
    if (rect.top < 80 || rect.top > window.innerHeight) {
      window.scrollTo({ top: window.scrollY + rect.top - 100, behavior: reduceMotion ? "auto" : "smooth" });
    }
    // `screenKey` pilote volontairement cet effet ; `reduceMotion` ne doit
    // jamais le redéclencher à lui seul (juste changer le style du défilement).
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [screenKey]);

  return setNode;
}
