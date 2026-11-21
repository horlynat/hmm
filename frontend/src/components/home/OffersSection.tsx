"use client";

import { useState, useSyncExternalStore } from "react";
import clsx from "clsx";
import { useLocale, useTranslations } from "next-intl";
import type { Offer } from "@/lib/types";
import { whatsappLink } from "@/lib/whatsapp";
import { formatFcfa } from "@/lib/format/fcfa";
import { CheckMark, OfferIcon, WhatsappIcon } from "./OfferIcon";

export const SITUATIONS = ["clients", "appli", "secu", "assur"] as const;
type Situation = (typeof SITUATIONS)[number];
type Filter = Situation | "all";

/** `#offres-secu` → "secu" ; toute autre ancre → null (liste blanche stricte). */
export function situationFromHash(hash: string): Situation | null {
  const match = /^#offres-([a-z]+)$/.exec(hash);
  const value = match?.[1];
  return value && (SITUATIONS as readonly string[]).includes(value) ? (value as Situation) : null;
}

function subscribeHash(callback: () => void) {
  window.addEventListener("hashchange", callback);
  return () => window.removeEventListener("hashchange", callback);
}

/**
 * Grille des offres publiées, filtrable par situation. Le filtre vient soit
 * d'un clic sur un bouton, soit de l'ancre (`#offres-<situation>`, posée par
 * les cartes « Votre besoin ») — la plus récente des deux l'emporte : un
 * choix au bouton est mémorisé avec l'ancre courante, et une nouvelle ancre
 * (clic sur une carte besoin) le remplace. `useSyncExternalStore` plutôt
 * qu'un setState dans un effet (règle react-hooks/set-state-in-effect).
 */
export function OffersSection({ offers }: { offers: Offer[] }) {
  const t = useTranslations("home.offers");
  const th = useTranslations("home.hero");
  const locale = useLocale();
  const hash = useSyncExternalStore(subscribeHash, () => window.location.hash, () => "");
  const [choice, setChoice] = useState<{ value: Filter; hash: string } | null>(null);

  const active: Filter = choice && choice.hash === hash ? choice.value : (situationFromHash(hash) ?? "all");
  const visible = active === "all" ? offers : offers.filter((offer) => offer.situation === active);

  return (
    <section id="offres" className="scroll-mt-20 px-6 py-20">
      {/* Cibles des liens « Votre besoin » : défilement même sans JavaScript. */}
      {SITUATIONS.map((s) => (
        <span key={s} id={`offres-${s}`} className="block scroll-mt-20" aria-hidden="true" />
      ))}
      <div className="mx-auto max-w-[1120px]">
        <span className="badge mb-3.5">{t("eyebrow")}</span>
        <h2 className="mb-2 text-[clamp(1.75rem,3.5vw,2.5rem)]">{t("title")}</h2>
        <p className="mb-8 max-w-[60ch] text-[var(--color-muted)]">{t("lede")}</p>

        <div role="group" aria-label={t("filterLabel")} className="mb-8 flex flex-wrap gap-2">
          {(["all", ...SITUATIONS] as const).map((value) => (
            <button
              key={value}
              type="button"
              aria-pressed={active === value}
              onClick={() => setChoice({ value, hash })}
              className={clsx(
                "rounded-full border px-3.5 py-1.5 text-sm transition-colors",
                active === value
                  ? "border-brand-accent bg-brand-accent/15 text-brand-dark"
                  : "border-[var(--border-soft)] text-[var(--color-muted)] hover:text-brand-dark",
              )}
            >
              {value === "all" ? t("filterAll") : t(`filters.${value}`)}
            </button>
          ))}
        </div>

        {/* Cartes étroites : la justification globale des <p> (globals.css)
            y creuse de grands blancs entre les mots → alignement à gauche,
            comme sur le prototype. */}
        {visible.length === 0 ? (
          <p className="text-sm text-[var(--color-muted)]">{t("noMatch")}</p>
        ) : (
          <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3 [&_p]:text-left">
            {visible.map((offer) => (
              <article key={offer.id} id={`offre-${offer.slug}`} className="card flex scroll-mt-24 flex-col">
                <div className="mb-4 flex items-center justify-between gap-3">
                  <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-primary/15 text-brand-primary">
                    <OfferIcon icon={offer.icon} />
                  </span>
                  {offer.featured && <span className="badge-accent">{t("featured")}</span>}
                </div>
                <p className="mb-1.5 text-sm text-brand-accent">{offer.pain}</p>
                <h3 className="mb-2 text-xl">{offer.title}</h3>
                <p className="mb-3 text-sm text-[var(--color-muted)]">{offer.cardText}</p>
                <p className="mb-5 text-xs text-[var(--color-muted)]">
                  <b className="text-brand-dark">{t("for")}</b> {offer.audience}
                </p>

                <div className="mt-auto">
                  <p className="mb-1 text-sm text-[var(--color-muted)]">
                    {offer.priceOnce ? th("priceOnce") : th("priceFrom")}{" "}
                    <b className="text-lg font-extrabold text-brand-dark" style={{ fontFamily: "var(--font-heading)" }}>
                      {formatFcfa(offer.price, locale)}
                    </b>
                  </p>
                  <p className="mb-5 text-xs text-[var(--color-muted)]">{th("readyIn", { delay: offer.delay })}</p>
                  <a
                    className="btn-whatsapp w-full"
                    href={whatsappLink(th("orderMessage", { title: offer.title }))}
                    target="_blank"
                    rel="noopener noreferrer"
                  >
                    <WhatsappIcon />
                    {th("order")}
                  </a>

                  <details className="group mt-4">
                    <summary className="cursor-pointer text-sm font-semibold text-brand-primary">{t("details")}</summary>
                    <div className="mt-4 space-y-5 text-sm">
                      <OfferList title={t("includesTitle")} items={offer.includes} />
                      <div>
                        <b className="mb-1.5 block">{t("needsTitle")}</b>
                        <p className="text-[var(--color-muted)]">{offer.clientNeeds}</p>
                      </div>
                      {offer.beforeItems.length > 0 && <OfferList title={t("beforeTitle")} items={offer.beforeItems} muted />}
                      {offer.afterItems.length > 0 && <OfferList title={t("afterTitle")} items={offer.afterItems} />}
                      {offer.pack.map((block, i) => (
                        <OfferList key={`${i}-${block.title}`} title={block.title} items={block.items} />
                      ))}
                      {offer.calendar.length > 0 && (
                        <div>
                          <b className="mb-1.5 block">{t("calendarTitle")}</b>
                          <ol className="list-none space-y-1.5 p-0">
                            {offer.calendar.map((step, i) => (
                              <li key={`${i}-${step.day}`} className="flex gap-3">
                                <span className="w-9 shrink-0 font-mono text-xs font-bold text-brand-primary">{step.day}</span>
                                <span className="text-[var(--color-muted)]">{step.text}</span>
                              </li>
                            ))}
                          </ol>
                        </div>
                      )}
                    </div>
                  </details>
                </div>
              </article>
            ))}
          </div>
        )}

        <div className="mt-12 grid grid-cols-1 gap-4 sm:grid-cols-3">
          {(["one", "two", "three"] as const).map((key) => (
            <div key={key} className="soft-card p-5">
              <b className="block" style={{ fontFamily: "var(--font-heading)" }}>{t(`commitments.${key}.title`)}</b>
              <span className="text-sm text-[var(--color-muted)]">{t(`commitments.${key}.desc`)}</span>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

function OfferList({ title, items, muted = false }: { title: string; items: string[]; muted?: boolean }) {
  return (
    <div>
      <b className="mb-1.5 block">{title}</b>
      <ul className="list-none space-y-1.5 p-0">
        {items.map((item, i) => (
          <li key={`${i}-${item}`} className="flex items-start gap-2 text-[var(--color-muted)]">
            <span className={muted ? "text-[var(--color-muted)]" : "text-success"}>
              {muted ? <span aria-hidden="true">–</span> : <CheckMark />}
            </span>
            {item}
          </li>
        ))}
      </ul>
    </div>
  );
}
