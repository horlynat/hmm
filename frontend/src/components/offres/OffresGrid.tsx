"use client";

import { useState } from "react";
import clsx from "clsx";
import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import type { Offer } from "@/lib/types";
import { whatsappLink } from "@/lib/whatsapp";
import { formatFcfa } from "@/lib/format/fcfa";
import { OfferIcon, WhatsappIcon } from "@/components/home/OfferIcon";

const SITUATIONS = ["clients", "appli", "secu", "assur"] as const;
type Filter = (typeof SITUATIONS)[number] | "all";

/**
 * Catalogue complet des offres (page dédiée `/offres`), filtrable par
 * situation. Chaque carte renvoie vers sa fiche complète (`/offres/[slug]`)
 * plutôt que de dupliquer tout le détail en ligne — contrairement à la
 * grille condensée de l'accueil (components/home/OffersSection.tsx), qui
 * garde son détail dépliable en page pour un visiteur qui ne veut pas
 * naviguer plus loin.
 *
 * Pas de synchronisation avec l'URL (contrairement à OffersSection, qui doit
 * recevoir le filtre posé par les cartes « Votre besoin » de l'accueil) :
 * cette page est elle-même la destination finale, un simple état local
 * suffit.
 */
export function OffresGrid({ offers }: { offers: Offer[] }) {
  const t = useTranslations("offers");
  const locale = useLocale();
  const [active, setActive] = useState<Filter>("all");

  const visible = active === "all" ? offers : offers.filter((offer) => offer.situation === active);

  return (
    <>
      <div role="group" aria-label={t("filterLabel")} className="mb-8 flex flex-wrap gap-2">
        {(["all", ...SITUATIONS] as const).map((value) => (
          <button
            key={value}
            type="button"
            aria-pressed={active === value}
            onClick={() => setActive(value)}
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

      {/* [&_p]:text-left : la justification globale des <p> (globals.css)
          creuse de grands blancs entre les mots dans ces cartes étroites. */}
      {visible.length === 0 ? (
        <p className="text-sm text-[var(--color-muted)]">{t("noMatch")}</p>
      ) : (
        <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3 [&_p]:text-left">
          {visible.map((offer) => (
            <article key={offer.id} className="card flex flex-col">
              <div className="mb-4 flex items-center justify-between gap-3">
                <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-primary/15 text-brand-primary">
                  <OfferIcon icon={offer.icon} />
                </span>
                {offer.featured && <span className="badge-accent">{t("featured")}</span>}
              </div>
              <p className="mb-1.5 text-sm text-brand-accent">{offer.pain}</p>
              <h2 className="mb-2 text-xl">{offer.title}</h2>
              <p className="mb-3 text-sm text-[var(--color-muted)]">{offer.cardText}</p>
              <p className="mb-5 text-xs text-[var(--color-muted)]">
                <b className="text-brand-dark">{t("for")}</b> {offer.audience}
              </p>

              <div className="mt-auto">
                <p className="mb-1 text-sm text-[var(--color-muted)]">
                  {offer.priceOnce ? t("priceOnce") : t("priceFrom")}{" "}
                  <b className="text-lg font-extrabold text-brand-dark" style={{ fontFamily: "var(--font-heading)" }}>
                    {formatFcfa(offer.price, locale)}
                  </b>
                </p>
                <p className="mb-5 text-xs text-[var(--color-muted)]">{t("readyIn", { delay: offer.delay })}</p>
                <div className="flex flex-col gap-2">
                  <a
                    className="btn-whatsapp w-full"
                    href={whatsappLink(t("detail.orderMessage", { title: offer.title }))}
                    target="_blank"
                    rel="noopener noreferrer"
                  >
                    <WhatsappIcon />
                    {t("detail.orderCta")}
                  </a>
                  <Link
                    href={{ pathname: "/offres/[slug]", params: { slug: offer.slug } }}
                    className="btn-secondary w-full text-center"
                  >
                    {t("seeDetail")}
                  </Link>
                </div>
              </div>
            </article>
          ))}
        </div>
      )}
    </>
  );
}
