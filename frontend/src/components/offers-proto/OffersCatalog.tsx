"use client";

import { useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import type { Offer } from "@/lib/types";
import { formatFcfa } from "@/lib/format/fcfa";
import { whatsappLink } from "@/lib/whatsapp";
import { OfferVisual, ProtoIcon } from "@/components/proto/ProtoSvg";
import type { ProtoIconName } from "@/components/proto/assets";
import { offerIcon } from "@/components/landing/offerHelpers";
import { OfferCatCard, audienceShort } from "./OfferCatCard";

/**
 * Catalogue des offres — markup du prototype (pOffers : `.pick2 .sitr .budr
 * .star .ogrp .ocat .oc .cmp2`), alimenté par les offres publiées :
 * filtres par situation et par budget (côté client, aucune requête),
 * offre vedette en tête, deux familles, tableau comparatif trié par prix.
 */
type Situation = "tous" | Offer["situation"];
type Budget = "tous" | "petit" | "moyen" | "grand";

const SITUATIONS: Situation[] = ["tous", "clients", "appli", "secu", "assur"];
const SITUATION_ICON: Record<Situation, ProtoIconName> = { tous: "spark", clients: "shop", appli: "arch", secu: "lock", assur: "umb" };
const BUDGETS: Budget[] = ["tous", "petit", "moyen", "grand"];
const GROUPS: Offer["category"][] = ["dev", "aud"];

export function budgetMatches(budget: Budget, price: number): boolean {
  if (budget === "petit") return price < 150000;
  if (budget === "moyen") return price >= 150000 && price <= 300000;
  if (budget === "grand") return price > 300000;
  return true;
}

/** Délai saisi en texte (« 7 jours », « 4 à 8 semaines », « 3 weeks ») -> jours, borne haute. */
export function delayDays(delay: string): number {
  const n = (delay.match(/\d+/g) ?? ["0"]).map(Number);
  const last = n[n.length - 1] ?? 0;
  return /semaine|week/i.test(delay) ? last * 7 : last;
}

export function OffersCatalog({ offers }: { offers: Offer[] }) {
  const t = useTranslations("catalog");
  const tl = useTranslations("landing.hero");
  const tc = useTranslations("landing.cta");
  const locale = useLocale();
  const [situation, setSituation] = useState<Situation>("tous");
  const [budget, setBudget] = useState<Budget>("tous");

  const list = offers.filter((o) => budgetMatches(budget, o.price) && (situation === "tous" || o.situation === situation));
  const star = list.find((o) => o.featured);
  const rest = list.filter((o) => o !== star);
  const filtered = situation !== "tous" || budget !== "tous";
  const reset = () => {
    setSituation("tous");
    setBudget("tous");
  };
  const priceLabel = (o: Offer) => (o.priceOnce ? tl("priceOnce") : tl("priceFrom"));
  const offerHref = (o: Offer) => ({ pathname: "/offres/[slug]" as const, params: { slug: o.slug } });

  const compared = [...offers].sort((a, b) => a.price - b.price);
  const maxP = Math.max(1, ...compared.map((o) => o.price));
  const maxD = Math.max(1, ...compared.map((o) => delayDays(o.delay)));

  return (
    <>
      <div className="pick2">
        <div>
          <span className="pkl">{t("situationLabel")}</span>
          <div className="sitr" role="group" aria-label={t("situationLabel")}>
            {SITUATIONS.map((s) => (
              <button key={s} type="button" aria-pressed={situation === s} onClick={() => setSituation(s)}>
                <span className="oic">
                  <ProtoIcon name={SITUATION_ICON[s]} />
                </span>
                {t(`situations.${s}`)}
              </button>
            ))}
          </div>
        </div>
        <div className="pkf">
          <div>
            <span className="pkl">{t("budgetLabel")}</span>
            <div className="budr" role="group" aria-label={t("budgetLabel")}>
              {BUDGETS.map((b) => (
                <button key={b} type="button" aria-pressed={budget === b} onClick={() => setBudget(b)}>
                  {t(`budgets.${b}`)}
                </button>
              ))}
            </div>
          </div>
          <p className="cnt2" aria-live="polite">
            {t.rich("count", { count: list.length, b: (c) => <b>{c}</b> })}
            {filtered && (
              <>
                {" · "}
                <button type="button" className="linkbtn" onClick={reset}>
                  {t("reset")}
                </button>
              </>
            )}
          </p>
        </div>
      </div>

      {star && (
        <Link className="star" href={offerHref(star)}>
          <div>
            <span className="tag">{t("featured")}</span>
            <h2>{star.headline}</h2>
            <p>{star.cardText}</p>
            <div className="price" style={{ margin: "22px 0 0" }}>
              <span className="p">
                {priceLabel(star)} <b>{formatFcfa(star.price, locale)}</b>
              </span>
              <span className="d">
                <i />
                {tl("readyIn", { delay: star.delay })}
              </span>
            </div>
            <span className="b b-w" style={{ marginTop: "24px" }}>
              <ProtoIcon name="wa" />
              {t("starCta")}
            </span>
          </div>
          <div className="starv">
            <OfferVisual slug={star.slug} previewType={star.previewType} />
          </div>
        </Link>
      )}

      {GROUPS.map((g) => {
        const items = rest.filter((o) => o.category === g);
        if (items.length === 0) return null;
        return (
          <div key={g} className="ogrp">
            <div className="ogh">
              <h2>{t(`groups.${g}`)}</h2>
              <p>{t("groupCount", { count: items.length })}</p>
            </div>
            <div className="ocat">
              {items.map((o) => (
                <OfferCatCard key={o.id} offer={o} />
              ))}
            </div>
          </div>
        );
      })}

      {list.length === 0 && (
        <div className="empty">
          <h3>{t("emptyTitle")}</h3>
          <p className="mut">{t("emptyText")}</p>
          <div style={{ display: "flex", gap: "12px", flexWrap: "wrap", justifyContent: "center", marginTop: "20px" }}>
            <button type="button" className="b b-g" onClick={reset}>
              {t("emptyAll")}
            </button>
            <a className="b b-w" href={whatsappLink(t("emptyMessage"))} target="_blank" rel="noopener noreferrer">
              <ProtoIcon name="wa" />
              {tc("whatsapp")}
            </a>
          </div>
        </div>
      )}

      {compared.length > 0 && (
        <div className="cmp2">
          <div className="ogh">
            <h2>{t("compareTitle")}</h2>
            <p>{t("compareSub")}</p>
          </div>
          <div className="cmh" aria-hidden="true">
            {(t.raw("compareCols") as string[]).map((c) => (
              <span key={c}>{c}</span>
            ))}
            <span />
          </div>
          {compared.map((o) => (
            <Link key={o.id} className={o.featured ? "cmr hot" : "cmr "} href={offerHref(o)}>
              <span className="cmo">
                <span className="oic">
                  <ProtoIcon name={offerIcon(o.icon)} />
                </span>
                <span>
                  <b>{o.title}</b>
                  <small>{audienceShort(o.audience)}</small>
                </span>
                {o.featured && <span className="tag">{t("compareHot")}</span>}
              </span>
              <span className="cmv">
                <b>{formatFcfa(o.price, locale)}</b>
                <i className="bar">
                  <i style={{ width: `${Math.max(8, (o.price / maxP) * 100)}%` }} />
                </i>
              </span>
              <span className="cmv">
                <b className="g">{o.delay}</b>
                <i className="bar d">
                  <i style={{ width: `${Math.max(8, (delayDays(o.delay) / maxD) * 100)}%` }} />
                </i>
              </span>
              <span className="cmgo">
                {t("compareGo")} <ProtoIcon name="ar" />
              </span>
            </Link>
          ))}
        </div>
      )}
    </>
  );
}
