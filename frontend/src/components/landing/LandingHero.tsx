"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import Image from "next/image";
import clsx from "clsx";
import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import type { Offer } from "@/lib/types";
import { whatsappLink } from "@/lib/whatsapp";
import { formatFcfa } from "@/lib/format/fcfa";
import { useReducedMotion } from "@/lib/useReducedMotion";
import { IdBadgeArt, ProtoIcon } from "@/components/proto/ProtoSvg";
import { OfferVisualOrPhoto } from "./offerHelpers";

/** Durée d'une diapositive — identique au prototype (DUR = 7000). */
export const SLIDE_DURATION_MS = 7000;

/**
 * Hero carrousel de l'accueil — markup et classes du prototype de refonte
 * (`.hero`, `.car`, `.by2`, `.track`, `.slide`, `.ctl`, `.dots`, `.arr`,
 * cf. proto-skin.css), alimenté par les offres « carrousel » du back-office.
 *
 * Comportement repris du prototype : défilement toutes les 7 s, pause au
 * survol et au focus (`.car.pause` fige aussi la barre de progression),
 * balayage tactile, aucune animation si l'utilisateur les a réduites.
 * Ajout volontaire (WCAG 2.2.2) : un bouton pause/lecture dans `.arr`, au même
 * style que les flèches — le prototype n'en avait pas.
 *
 * Diapositives inactives : `aria-hidden` + `inert` (ni focus ni lecteur
 * d'écran), comme le `tabindex=-1` du prototype mais en plus robuste.
 */
export function LandingHero({ offers, profilePhotoUrl }: { offers: Offer[]; profilePhotoUrl?: string | null }) {
  const t = useTranslations("landing");
  const locale = useLocale();
  const reduceMotion = useReducedMotion();
  const [index, setIndex] = useState(0);
  const [hovered, setHovered] = useState(false);
  const [focused, setFocused] = useState(false);
  const [userPaused, setUserPaused] = useState(false);
  const touchX = useRef<number | null>(null);
  const total = offers.length;

  const go = useCallback((next: number) => setIndex(((next % total) + total) % total), [total]);
  const paused = hovered || focused || userPaused;

  useEffect(() => {
    if (total < 2 || paused || reduceMotion) return;
    const id = window.setTimeout(() => go(index + 1), SLIDE_DURATION_MS);
    return () => window.clearTimeout(id);
  }, [index, paused, reduceMotion, total, go]);

  const identity = (
    <div className="by2">
      <span className="byr">
        {profilePhotoUrl ? (
          // Photo réelle (À propos, back-office) si elle existe, habillée par
          // la règle `.byr>img` du prototype (cercle, cadrage visage). Surtout
          // pas `fill` : son `position:absolute; inset:0` en ligne couvrirait
          // le padding de 4 px, donc l'anneau dégradé du badge (contre-audit).
          // Sinon inchangé : le SVG du prototype porte role="img" et son libellé.
          <Image src={profilePhotoUrl} alt={t("identity.name")} width={132} height={132} sizes="132px" />
        ) : (
          <IdBadgeArt />
        )}
      </span>
      <div className="byt">
        <b>{t("identity.name")}</b>
        <span className="t1">{t("identity.role1")}</span>
        <span className="t2">{t("identity.role2")}</span>
      </div>
    </div>
  );

  // Aucune offre publiée : identité + message de repli, même habillage.
  if (total === 0) {
    return (
      <section className="hero">
        <div className="car">
          <div className="w">
            {identity}
            <div className="track">
              <div className="slide on">
                <div>
                  <h1 className="ht">{t("hero.fallbackTitle")}</h1>
                  <p className="lead">{t("hero.fallbackLead")}</p>
                  <div style={{ display: "flex", flexWrap: "wrap", gap: 12, marginTop: 30 }}>
                    <a className="b b-w" href={whatsappLink(t("cta.message"))} target="_blank" rel="noopener noreferrer">
                      <ProtoIcon name="wa" />
                      {t("cta.whatsapp")}
                    </a>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
    );
  }

  return (
    <section className="hero" aria-roledescription="carrousel" aria-label={t("hero.carouselLabel")}>
      <div
        className={clsx("car", paused && "pause")}
        onMouseEnter={() => setHovered(true)}
        onMouseLeave={() => setHovered(false)}
        onFocus={() => setFocused(true)}
        onBlur={(e) => {
          if (!e.currentTarget.contains(e.relatedTarget as Node | null)) setFocused(false);
        }}
        onTouchStart={(e) => {
          touchX.current = e.touches[0]?.clientX ?? null;
        }}
        onTouchEnd={(e) => {
          if (touchX.current === null) return;
          const dx = (e.changedTouches[0]?.clientX ?? touchX.current) - touchX.current;
          touchX.current = null;
          if (Math.abs(dx) > 50) go(index + (dx < 0 ? 1 : -1));
        }}
      >
        <div className="w">
          {identity}
          <div className="track">
            {offers.map((offer, i) => {
              const active = i === index;
              const Heading = i === 0 ? "h1" : "h2";
              return (
                <div
                  key={offer.id}
                  className={clsx("slide", active && "on")}
                  role="group"
                  aria-roledescription={t("hero.slideRole")}
                  aria-label={t("hero.slideLabel", { index: i + 1, total })}
                  aria-hidden={active ? undefined : true}
                  inert={!active}
                >
                  <div>
                    <p className="pain">{offer.pain}</p>
                    <Heading className="ht">{offer.headline}</Heading>
                    <p className="lead">{offer.leadText}</p>
                    <div className="price">
                      <span className="p">
                        {offer.priceOnce ? t("hero.priceOnce") : t("hero.priceFrom")} <b>{formatFcfa(offer.price, locale)}</b>
                      </span>
                      <span className="d">
                        <i />
                        {t("hero.readyIn", { delay: offer.delay })}
                      </span>
                    </div>
                    <div style={{ display: "flex", flexWrap: "wrap", gap: 12 }}>
                      <a
                        className="b b-w"
                        href={whatsappLink(t("hero.orderMessage", { title: offer.title }))}
                        target="_blank"
                        rel="noopener noreferrer"
                      >
                        <ProtoIcon name="wa" />
                        {t("hero.order")}
                      </a>
                      <Link className="b b-g" href={{ pathname: "/offres/[slug]", params: { slug: offer.slug } }}>
                        {t("hero.seeOffer")}
                      </Link>
                    </div>
                  </div>
                  <div className="vis">
                    <OfferVisualOrPhoto offer={offer} sizes="(max-width: 900px) 100vw, 520px" />
                  </div>
                </div>
              );
            })}
          </div>
        </div>

        {total > 1 && (
          <div className="ctl">
            <div className="w">
              <span className="cnt" aria-hidden="true">
                <b>{String(index + 1).padStart(2, "0")}</b> / {String(total).padStart(2, "0")}
              </span>
              <div className="dots" role="tablist" aria-label={t("hero.chooseOffer")}>
                {offers.map((offer, i) => (
                  <button
                    key={offer.id}
                    type="button"
                    role="tab"
                    aria-selected={i === index}
                    aria-label={offer.title}
                    className={clsx("dot", i === index && "on", i < index && "done")}
                    onClick={() => go(i)}
                  >
                    <span>{offer.shortTitle}</span>
                    {/* `key` : relance l'animation de progression à chaque changement. */}
                    <i key={i === index ? `on-${index}` : "off"} style={{ ["--dur" as string]: `${SLIDE_DURATION_MS}ms` }} />
                  </button>
                ))}
              </div>
              <div className="arr">
                <button type="button" aria-label={t("hero.previous")} onClick={() => go(index - 1)}>
                  <ProtoIcon name="al" />
                </button>
                <button
                  type="button"
                  aria-label={userPaused ? t("hero.play") : t("hero.pause")}
                  aria-pressed={userPaused}
                  onClick={() => setUserPaused((v) => !v)}
                >
                  <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round">
                    {userPaused ? <path d="M7 5l12 7-12 7z" /> : <path d="M8 5v14M16 5v14" />}
                  </svg>
                </button>
                <button type="button" aria-label={t("hero.next")} onClick={() => go(index + 1)}>
                  <ProtoIcon name="ar" />
                </button>
              </div>
            </div>
          </div>
        )}
      </div>
    </section>
  );
}
