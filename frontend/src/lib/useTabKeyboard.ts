import { useRef, type KeyboardEvent } from "react";

/**
 * Navigation clavier d'une liste d'onglets horizontale (motif ARIA « tabs »,
 * activation manuelle) : seul l'onglet actif est dans l'ordre de tabulation
 * (tabindex 0, les autres -1) ; ←/→ (bouclant), Début et Fin déplacent le
 * focus d'un onglet à l'autre SANS l'activer ; Entrée ou Espace l'activent
 * (clic natif du bouton). Sans ça, un lecteur d'écran annonce « onglet »
 * sans que les flèches attendues ne fonctionnent.
 */
export function useTabKeyboard<T extends string>(values: readonly T[], selected: T) {
  const buttons = useRef(new Map<T, HTMLButtonElement | null>());

  function onKeyDown(event: KeyboardEvent<HTMLButtonElement>, current: T) {
    const i = values.indexOf(current);
    const n = values.length;
    let next: number | null = null;
    if (event.key === "ArrowRight") next = (i + 1) % n;
    else if (event.key === "ArrowLeft") next = (i - 1 + n) % n;
    else if (event.key === "Home") next = 0;
    else if (event.key === "End") next = n - 1;
    if (next === null) return;
    event.preventDefault();
    buttons.current.get(values[next])?.focus();
  }

  function tabProps(value: T) {
    return {
      ref: (el: HTMLButtonElement | null) => {
        buttons.current.set(value, el);
      },
      "aria-selected": value === selected,
      tabIndex: value === selected ? 0 : -1,
      onKeyDown: (event: KeyboardEvent<HTMLButtonElement>) => onKeyDown(event, value),
    };
  }

  return { tabProps };
}
