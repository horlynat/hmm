"use client";

import { useCallback, useEffect, useState } from "react";
import clsx from "clsx";
import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import type { Offer } from "@/lib/types";
import { whatsappLink } from "@/lib/whatsapp";
import { formatFcfa } from "@/lib/format/fcfa";
import { useReducedMotion } from "@/lib/useReducedMotion";
import { CheckMark, OfferIcon, WhatsappIcon } from "./OfferIcon";

const SLIDE_DURATION_MS = 7000;

/**
 * Carrousel des offres du hero (refonte 2026-11, prototype « Portail
 * Digital »). Toutes les diapositives sont rendues côté serveur (contenu
 * indexable, aucun saut au chargement) ; seule l'active est visible, les
 * autres sont `inert` (ni focus clavier ni lecteur d'écran).
 *
 * Accessibilité :
 *  - défilement automatique suspendu au survol, au focus clavier, et
 *    désactivé si l'utilisateur a réduit les animations ;
 *  - bouton pause/lecture explicite (WCAG 2.2.2 : tout contenu qui défile
 *    seul plus de 5 s doit pouvoir être mis en pause) ;
 *  - motif « carousel » ARIA : région étiquetée, diapositives en `group`
 *    avec « n sur N », pastilles annonçant l'offre ciblée.
 *
 * Le titre de page (h1) est porté par la page, pas par une diapositive :
 * il ne change pas quand le carrousel tourne.
 */
/** `:focus-visible` (focus clavier) ; prudent par défaut si non supporté. */
function isFocusVisible(el: Element): boolean {
  try {
    return el.matches(":focus-visible");
  } catch {
    return true;
  }
}

export function OfferHeroCarousel({ offers }: { offers: Offer[] }) {
  const t = useTranslations("home.hero");
  const locale = useLocale();
  const reduceMotion = useReducedMotion();
  const [index, setIndex] = useState(0);
  const [paused, setPaused] = useState(false);
  const [hovered, setHovered] = useState(false);
  const [keyboardFocus, setKeyboardFocus] = useState(false);
  const total = offers.length;

  const go = useCallback((next: number) => setIndex(((next % total) + total) % total), [total]);

  const autoplay = total > 1 && !paused && !hovered && !keyboardFocus && !reduceMotion;
  useEffect(() => {
    if (!autoplay) return;
    const id = window.setTimeout(() => go(index + 1), SLIDE_DURATION_MS);
    return () => window.clearTimeout(id);
  }, [autoplay, index, go]);

  if (total === 0) return null;

  return (
    <section
      aria-roledescription="carousel"
      aria-label={t("carouselLabel")}
      onMouseEnter={() => setHovered(true)}
      onMouseLeave={() => setHovered(false)}
      // Pause au focus CLAVIER seulement : un clic souris (ex. sur « lecture »)
      // laisse le focus sur le bouton, et la reprise ne doit pas attendre
      // qu'il le perde — le survol couvre déjà la souris.
      onFocus={(e) => setKeyboardFocus(isFocusVisible(e.target))}
      onBlur={(e) => {
        if (!e.currentTarget.contains(e.relatedTarget as Node | null)) setKeyboardFocus(false);
      }}
    >
      <div className="relative">
        {offers.map((offer, i) => {
          const active = i === index;
          return (
            <div
              key={offer.id}
              role="group"
              aria-roledescription="slide"
              aria-label={t("slideLabel", { index: i + 1, total })}
              inert={!active}
              className={clsx("grid items-center gap-10 lg:grid-cols-[1.15fr_0.85fr]", active ? "hero-slide-in" : "hidden")}
            >
              <div>
                <p className="mb-3 text-[0.95rem] font-medium text-brand-accent">{offer.pain}</p>
                <h2 className="mb-4 text-[clamp(2rem,4.6vw,3.4rem)] leading-[1.05]">{offer.headline}</h2>
                <p className="mb-7 max-w-[54ch] text-[1.05rem] text-[var(--color-muted)]">{offer.leadText}</p>

                <div className="mb-7 flex flex-wrap items-center gap-x-6 gap-y-2">
                  <span className="text-sm text-[var(--color-muted)]">
                    {offer.priceOnce ? t("priceOnce") : t("priceFrom")}{" "}
                    <b className="text-xl font-extrabold text-brand-dark" style={{ fontFamily: "var(--font-heading)" }}>
                      {formatFcfa(offer.price, locale)}
                    </b>
                  </span>
                  <span className="inline-flex items-center gap-2 text-sm text-[var(--color-muted)]">
                    <span aria-hidden="true" className="h-2 w-2 rounded-full bg-success" />
                    {t("readyIn", { delay: offer.delay })}
                  </span>
                </div>

                <div className="flex flex-wrap gap-3">
                  <a
                    className="btn-whatsapp"
                    href={whatsappLink(t("orderMessage", { title: offer.title }))}
                    target="_blank"
                    rel="noopener noreferrer"
                  >
                    <WhatsappIcon />
                    {t("order")}
                  </a>
                  {/* Fiche dédiée (R4, /offres/[slug]) plutôt que l'ancre
                      en page `#offre-${slug}` utilisée avant sa création. */}
                  <Link
                    className="btn-secondary"
                    href={{ pathname: "/offres/[slug]", params: { slug: offer.slug } }}
                  >
                    {t("seeOffer")}
                  </Link>
                </div>
              </div>

              <div className="soft-card hidden p-7 lg:block" aria-hidden="true">
                <div className="mb-5 flex items-center gap-3">
                  <span className="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-primary/15 text-brand-primary">
                    <OfferIcon icon={offer.icon} size={24} />
                  </span>
                  <b className="text-lg" style={{ fontFamily: "var(--font-heading)" }}>{offer.title}</b>
                </div>
                <ul className="space-y-3">
                  {offer.includes.slice(0, 4).map((item, j) => (
                    <li key={`${j}-${item}`} className="flex items-start gap-2.5 text-sm">
                      <span className="text-success"><CheckMark /></span>
                      {item}
                    </li>
                  ))}
                </ul>
              </div>
            </div>
          );
        })}
      </div>

      {total > 1 && (
        <div className="mt-10 flex flex-wrap items-center gap-4">
          <span aria-hidden="true" className="font-mono text-sm text-[var(--color-muted)]">
            <b className="text-brand-dark">{String(index + 1).padStart(2, "0")}</b> / {String(total).padStart(2, "0")}
          </span>
          <div role="group" aria-label={t("chooseOffer")} className="flex flex-1 flex-wrap gap-2">
            {offers.map((offer, i) => (
              <button
                key={offer.id}
                type="button"
                onClick={() => go(i)}
                aria-current={i === index ? "true" : undefined}
                className={clsx(
                  "rounded-full border px-3 py-1.5 font-mono text-[0.72rem] transition-colors",
                  i === index
                    ? "border-brand-accent bg-brand-accent/15 text-brand-dark"
                    : "border-[var(--border-soft)] text-[var(--color-muted)] hover:text-brand-dark",
                )}
              >
                {offer.shortTitle}
              </button>
            ))}
          </div>
          <div className="flex gap-2">
            <button type="button" onClick={() => setPaused((p) => !p)} aria-pressed={paused} aria-label={paused ? t("play") : t("pause")} className="carousel-ctl">
              {paused ? "▶" : "❚❚"}
            </button>
            <button type="button" onClick={() => go(index - 1)} aria-label={t("previous")} className="carousel-ctl">
              ←
            </button>
            <button type="button" onClick={() => go(index + 1)} aria-label={t("next")} className="carousel-ctl">
              →
            </button>
          </div>
        </div>
      )}
    </section>
  );
}
