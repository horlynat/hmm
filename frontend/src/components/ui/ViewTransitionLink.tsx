"use client";

import type { ComponentProps, MouseEvent } from "react";
import { Link } from "@/i18n/navigation";
import { useViewTransitionNavigate } from "@/lib/useViewTransitionNavigation";

interface ViewTransitionLinkProps extends ComponentProps<typeof Link> {
  /**
   * Doit correspondre exactement au `viewTransitionName` posé (via `style`,
   * sur un élément portant aussi la classe `.vt-target`) sur l'élément
   * équivalent de la page de destination — ex. l'image d'une carte projet et
   * l'image de couverture de sa page de détail partagent le même nom pour
   * "morphir" l'une vers l'autre pendant la transition. Omis = transition de
   * page simple (fondu), sans élément partagé animé.
   */
  viewTransitionName?: string;
}

/**
 * `<Link>` de l'app, navigation via l'API View Transitions du navigateur au
 * lieu d'un changement de page instantané — cf. useViewTransitionNavigation
 * pour le pourquoi (composant React `<ViewTransition>` indisponible sur la
 * version de React stable de ce projet). Reste un vrai lien `<a>` en dessous
 * (clic droit "ouvrir dans un nouvel onglet", Ctrl/Cmd+clic, etc. continuent
 * de fonctionner normalement — seul le clic gauche simple est intercepté).
 */
export function ViewTransitionLink({ href, viewTransitionName, onClick, ...props }: ViewTransitionLinkProps) {
  const navigate = useViewTransitionNavigate();

  function handleClick(event: MouseEvent<HTMLAnchorElement>) {
    onClick?.(event);
    if (event.defaultPrevented) return;
    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
      return;
    }
    event.preventDefault();
    navigate(href, viewTransitionName);
  }

  return <Link href={href} onClick={handleClick} {...props} />;
}
