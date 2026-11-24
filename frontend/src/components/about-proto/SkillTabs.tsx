"use client";

import { useRef, useState, type KeyboardEvent } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import type { Offer } from "@/lib/types";
import { formatFcfa } from "@/lib/format/fcfa";
import { ProtoIcon } from "@/components/proto/ProtoSvg";
import type { ProtoIconName } from "@/components/proto/assets";
import { offerIcon } from "@/components/landing/offerHelpers";

export type SkillDomain = {
  icon: ProtoIconName;
  title: string;
  short: string;
  text: string;
  do: string[];
  tools: string[];
  /** Offres publiées liées à ce domaine (déjà filtrées côté serveur). */
  offers: Offer[];
  /** Réalisation publiée servant de preuve, s'il y en a une. */
  proof: { slug: string; title: string } | null;
};

/**
 * Onglets « Compétences » du prototype (skills()/skPanel() : `.skt .skn .skp
 * .skg .skof3 .skog .skpr`), avec le fondu `.fade` au changement d'onglet.
 * Clavier : flèches gauche/droite/haut/bas, Début, Fin (motif ARIA tabs).
 */
export function SkillTabs({ domains }: { domains: SkillDomain[] }) {
  const t = useTranslations("aboutPage");
  const tl = useTranslations("landing.hero");
  const locale = useLocale();
  const [active, setActive] = useState(0);
  const [fadeKey, setFadeKey] = useState(0);
  const tabs = useRef<(HTMLButtonElement | null)[]>([]);
  const d = domains[active];
  if (!d) return null;

  function select(i: number) {
    setActive(i);
    setFadeKey((k) => k + 1);
    tabs.current[i]?.focus();
  }

  function onKeyDown(e: KeyboardEvent<HTMLDivElement>) {
    const n = domains.length;
    const moves: Record<string, number> = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 };
    if (e.key in moves) select((active + moves[e.key] + n) % n);
    else if (e.key === "Home") select(0);
    else if (e.key === "End") select(n - 1);
    else return;
    e.preventDefault();
  }

  return (
    <>
      <div className="skt">
        <div className="skn" role="tablist" aria-label={t("skillsTabs")} onKeyDown={onKeyDown}>
          {domains.map((s, i) => (
            <button
              key={s.title}
              ref={(el) => {
                tabs.current[i] = el;
              }}
              type="button"
              role="tab"
              id={`sk-tab-${i}`}
              aria-selected={i === active}
              aria-controls="skp"
              tabIndex={i === active ? 0 : -1}
              onClick={() => select(i)}
            >
              <span className="oic">
                <ProtoIcon name={s.icon} />
              </span>
              <span>
                <b>{s.title}</b>
                <small>{s.short}</small>
              </span>
            </button>
          ))}
        </div>
        <div key={fadeKey} className={fadeKey ? "skp fade" : "skp"} id="skp" role="tabpanel" aria-labelledby={`sk-tab-${active}`}>
          <div className="skh">
            <span className="oic">
              <ProtoIcon name={d.icon} />
            </span>
            <div>
              <h3>{d.title}</h3>
              <p>{d.text}</p>
            </div>
          </div>
          <div className="skg">
            <div>
              <p className="skl">{t("doFor")}</p>
              <ul className="chk" style={{ marginTop: "0" }}>
                {d.do.map((x) => (
                  <li key={x}>
                    <i>✓</i>
                    {x}
                  </li>
                ))}
              </ul>
            </div>
            <div>
              <p className="skl">{t("tools")}</p>
              <div className="chips">
                {d.tools.map((x) => (
                  <span key={x} className="chip">
                    {x}
                  </span>
                ))}
              </div>
            </div>
          </div>
          {(d.offers.length > 0 || d.proof) && (
            <div className="skof3">
              {d.offers.length > 0 && (
                <>
                  <p className="skl">{t("linkedOffers")}</p>
                  <div className="skog">
                    {d.offers.map((o) => (
                      <Link key={o.id} href={{ pathname: "/offres/[slug]", params: { slug: o.slug } }}>
                        <span className="skoh">
                          <span className="oic">
                            <ProtoIcon name={offerIcon(o.icon)} />
                          </span>
                          <ProtoIcon name="ar" />
                        </span>
                        <b>{o.title}</b>
                        <span className="skom">
                          <span>
                            <small>{o.priceOnce ? tl("priceOnce") : tl("priceFrom")}</small>
                            {formatFcfa(o.price, locale)}
                          </span>
                          <span>
                            <small>{t("delay")}</small>
                            {o.delay}
                          </span>
                        </span>
                      </Link>
                    ))}
                  </div>
                </>
              )}
              {d.proof && (
                <Link className="skpr" href={{ pathname: "/realisations/[slug]", params: { slug: d.proof.slug } }}>
                  {t("proof", { title: d.proof.title })} <ProtoIcon name="ar" />
                </Link>
              )}
            </div>
          )}
        </div>
      </div>
      <p className="dm" style={{ fontSize: "14px", marginTop: "18px" }}>
        {t("skillsNote")}
      </p>
    </>
  );
}
