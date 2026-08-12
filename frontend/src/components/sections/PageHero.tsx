import type { ReactNode } from "react";
import clsx from "clsx";
import { Badge, HeroBackground } from "@/components/ui";

interface PageHeroProps {
  /** Contenu du badge d'accroche (généralement du texte, `RoleRotator` sur la home). */
  eyebrow: ReactNode;
  /**
   * Élément additionnel affiché à côté du badge d'accroche (ex. badge
   * "Fondateur..." sur la home) — quand fourni, `eyebrow` + `eyebrowExtra`
   * sont alignés sur une même ligne plutôt que le badge seul.
   */
  eyebrowExtra?: ReactNode;
  title: ReactNode;
  /** Partie du titre mise en avant (`text-brand-primary`), rendue après `title`. */
  titleAccent?: ReactNode;
  /** `true` = saut de ligne forcé avant `titleAccent` (home) ; défaut : accent sur la même ligne, retour naturel selon la largeur. */
  titleAccentOnNewLine?: boolean;
  /** Ex. `"max-w-[22ch]"` — aucune contrainte de largeur par défaut. */
  titleClassName?: string;
  subtitle: ReactNode;
  /** Ex. `"max-w-[60ch]"` — défaut `"max-w-[48ch]"`. */
  subtitleClassName?: string;
  /**
   * Colonne de droite : illustration du hero (diagramme, carte profil,
   * cartes stats...). Obligatoire — toutes les sections hero du site
   * partagent la même structure deux colonnes (message à gauche,
   * illustration à droite), il n'existe plus de variante une-colonne.
   */
  aside: ReactNode;
  /**
   * Contenu propre à chaque page sous le sous-titre (CTA, badges tech...) —
   * laissé à la charge de la page plutôt que transformé en props dédiées,
   * car il varie trop d'une page à l'autre pour être généralisé sans
   * devenir une usine à gaz.
   */
  children?: ReactNode;
  /**
   * Contenu pleine largeur sous la grille message/illustration, toujours
   * dans la même `section` (ex. carte stats + témoignage de la home) —
   * distinct de `children` qui reste dans la colonne de gauche.
   */
  footer?: ReactNode;
}

/**
 * Structure commune, identique sur toutes les pages publiques, des sections
 * hero — extraite du hero de la home (section + `HeroBackground` + badge
 * d'accroche animé + `h1` + sous-titre animé + grille deux colonnes message/
 * illustration), jusqu'ici dupliquée avec des variantes divergentes dans 9
 * fichiers (Aide en une colonne centrée, pb-12 vs pb-20 selon les pages...).
 * `aside` est volontairement obligatoire : plus aucune page ne doit s'écarter
 * du schéma deux colonnes. Le `h1` n'est volontairement PAS animé (pas de
 * classe `hero-in`) : c'est le candidat LCP le plus probable de chaque page,
 * un fondu ajouterait son délai+durée au LCP mesuré.
 */
export function PageHero({
  eyebrow,
  eyebrowExtra,
  title,
  titleAccent,
  titleAccentOnNewLine = false,
  titleClassName,
  subtitle,
  subtitleClassName = "max-w-[48ch]",
  aside,
  children,
  footer,
}: PageHeroProps) {
  return (
    <section className="relative overflow-hidden px-6 pt-16 pb-20">
      <HeroBackground />
      <div className="relative mx-auto grid max-w-[1120px] gap-12 md:grid-cols-[1.05fr_0.95fr] md:items-center">
        <div>
          {eyebrowExtra ? (
            <div
              className="hero-in mb-4 flex flex-wrap items-center gap-x-4 gap-y-2"
              style={{ animationDelay: "0s" }}
            >
              <Badge variant="accent">{eyebrow}</Badge>
              {eyebrowExtra}
            </div>
          ) : (
            <Badge variant="accent" className="hero-in mb-4" style={{ animationDelay: "0s" }}>
              {eyebrow}
            </Badge>
          )}
          <h1 className={clsx("mb-5 text-[clamp(1.75rem,3vw,2.75rem)] leading-[1.25]", titleClassName)}>
            {title}
            {titleAccent && titleAccentOnNewLine && <br />}
            {titleAccent && (
              <span className="text-brand-primary">
                {titleAccentOnNewLine ? titleAccent : <> {titleAccent}</>}
              </span>
            )}
          </h1>
          <p
            className={clsx("hero-in text-[1.05rem] opacity-75", children && "mb-7", subtitleClassName)}
            style={{ animationDelay: "0.16s" }}
          >
            {subtitle}
          </p>
          {children}
        </div>
        {aside}
      </div>
      {footer}
    </section>
  );
}
