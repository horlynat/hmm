import { getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";
import type { Offer, Project } from "@/lib/types";
import { formatFcfa } from "@/lib/format/fcfa";
import { splitPayment } from "@/components/landing/offerHelpers";
import { getExcerpt } from "@/lib/text";
import { whatsappLink } from "@/lib/whatsapp";
import { OfferVisual, ProtoIcon } from "@/components/proto/ProtoSvg";
import { ProtoCta } from "@/components/proto/ProtoSections";
import { ProjectImage } from "@/components/landing/SiteHome";
import { OfferCatCard } from "./OfferCatCard";

/**
 * Fiche d'une offre — markup du prototype (pOffer : `.ph.oh .ohg .od .odm
 * .ba .pv .pk .cal .needbox .eng3 .faq2 .proof .aside .buy .mbar`),
 * alimentée par l'offre du back-office. Les blocs sans donnée sont masqués ;
 * les mentions provisoires du prototype (« [conditions à définir] ») ne sont
 * pas publiées.
 */
type Row = [string, string, string];

function PreviewTable({ kind, t }: { kind: "report" | "risk" | "roadmap"; t: Awaited<ReturnType<typeof getTranslations>> }) {
  const cols = t.raw(`pv.${kind}.cols`) as string[];
  const rows = t.raw(`pv.${kind}.rows`) as Row[];
  return (
    <div className="pv">
      <div className="pvh">
        <b>{t(`pv.${kind}.title`)}</b>
        <span>{t("pv.sample")}</span>
      </div>
      <table className="pvt">
        <thead>
          <tr>
            {cols.map((c) => (
              <th key={c}>{c}</th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map(([a, b, c]) =>
            kind === "roadmap" ? (
              <tr key={a}>
                <td>
                  <span className="lvl b">{a}</span>
                </td>
                <td>{b}</td>
                <td>{c}</td>
              </tr>
            ) : (
              <tr key={a}>
                <td>{a}</td>
                <td>
                  <span className={`lvl ${b}`}>{t(`pv.${kind}.levels.${b}`)}</span>
                </td>
                <td>{c}</td>
              </tr>
            ),
          )}
        </tbody>
      </table>
    </div>
  );
}

export async function OfferDetail({
  locale,
  offer: o,
  offers,
  proof,
}: {
  locale: string;
  offer: Offer;
  offers: Offer[];
  proof: Project | null;
}) {
  const [t, tc, tl, tp] = await Promise.all([
    getTranslations({ locale, namespace: "offerPage" }),
    getTranslations({ locale, namespace: "catalog" }),
    getTranslations({ locale, namespace: "landing.hero" }),
    getTranslations({ locale, namespace: "landing.projects" }),
  ]);
  const price = formatFcfa(o.price, locale);
  const priceLabel = o.priceOnce ? tl("priceOnce") : tl("priceFrom");
  const buy = whatsappLink(tl("orderMessage", { title: o.title }));
  const others = offers
    .filter((z) => z.id !== o.id && z.category === o.category)
    .concat(offers.filter((z) => z.category !== o.category))
    .slice(0, 3);
  const faq: [string, string][] = [
    ...(o.priceOnce ? [[(t.raw("faqOnce") as string[])[0], t("faqOnce.1", { price })] as [string, string]] : []),
    // Offre à prix unique (assistant WhatsApp) : réduction contre un témoignage filmé.
    ...(o.priceOnce ? [t.raw("faqTestimonial") as [string, string]] : []),
    [(t.raw("faqDelay") as string[])[0], t("faqDelay.1", { delay: o.delay })],
    t.raw("faqPrice") as [string, string],
    t.raw("faqAfter") as [string, string],
  ];

  let preview;
  if (o.previewType === "report" || o.previewType === "risk" || o.previewType === "roadmap") {
    preview = <PreviewTable kind={o.previewType} t={t} />;
  } else if (o.previewType === "chat") {
    preview = (
      <div className="pv pvv">
        <div className="pvh">
          <b>{t("pv.chatTitle")}</b>
          <Link className="lnk" href={{ pathname: "/", hash: "demo" }}>
            {t("pv.chatLink")}
          </Link>
        </div>
        <div className="pvi">
          <OfferVisual slug="whatsapp" previewType="chat" />
        </div>
      </div>
    );
  } else {
    preview = (
      <div className="pv pvv">
        <div className="pvh">
          <b>{t("pv.overview")}</b>
          <span>{t("pv.illustration")}</span>
        </div>
        <div className="pvi">
          <OfferVisual slug={o.previewType === "flow" ? "automatisation" : o.slug} previewType={o.previewType} />
        </div>
      </div>
    );
  }

  return (
    <>
      <section className="ph oh">
        <div className="w">
          <p className="crumb">
            <Link href="/">{tc("crumbHome")}</Link> / <Link href="/offres">{tc("crumb")}</Link> / {o.title}
          </p>
          <div className="ohg">
            <div>
              <p className="pain">{o.pain}</p>
              <h1 className="ht">{o.headline}</h1>
              <p>{o.leadText}</p>
              <div className="price">
                <span className="p">
                  {priceLabel} <b>{price}</b>
                </span>
                <span className="d">
                  <i />
                  {tl("readyIn", { delay: o.delay })}
                </span>
              </div>
              <div style={{ display: "flex", gap: "12px", flexWrap: "wrap" }}>
                <a className="b b-w" href={buy} target="_blank" rel="noopener noreferrer">
                  <ProtoIcon name="wa" />
                  {t("order")}
                </a>
                <a className="b b-g" href={whatsappLink(t("askMessage", { title: o.title }))} target="_blank" rel="noopener noreferrer">
                  {t("ask")}
                </a>
              </div>
            </div>
            <div className="vis">
              <OfferVisual slug={o.previewType === "flow" ? "automatisation" : o.slug} previewType={o.previewType} />
            </div>
          </div>
        </div>
      </section>

      <section className="sec" style={{ paddingTop: "72px" }}>
        <div className="w od">
          <div className="odm">
            {(o.beforeItems.length > 0 || o.afterItems.length > 0) && (
              <section>
                <h2>{t("changeTitle")}</h2>
                <div className="ba">
                  <div className="bef">
                    <b>{t("before")}</b>
                    <ul>
                      {o.beforeItems.map((x) => (
                        <li key={x}>{x}</li>
                      ))}
                    </ul>
                  </div>
                  <div className="aft">
                    <b>{t("after")}</b>
                    <ul>
                      {o.afterItems.map((x) => (
                        <li key={x}>{x}</li>
                      ))}
                    </ul>
                  </div>
                </div>
              </section>
            )}

            <section>
              <h2>{t("resultTitle")}</h2>
              {preview}
            </section>

            {o.pack.length === 0 && o.includes.length > 0 && (
              <section>
                <h2>{t("includesTitle")}</h2>
                <ul className="chk" style={{ marginTop: 0 }}>
                  {o.includes.map((x) => (
                    <li key={x}>
                      <i>✓</i>
                      {x}
                    </li>
                  ))}
                </ul>
              </section>
            )}

            {o.pack.length > 0 && (
              <section>
                <h2>{t("packTitle")}</h2>
                <div className="pk">
                  <div className="pkl">
                    {o.pack.map((g) => (
                      <div key={g.title}>
                        <b>{g.title}</b>
                        <ul>
                          {g.items.map((z) => (
                            <li key={z}>
                              <i>✓</i>
                              {z}
                            </li>
                          ))}
                        </ul>
                      </div>
                    ))}
                  </div>
                  <div className="pkr">
                    <div className="pkp">
                      <small>{o.priceOnce ? t("packOnce") : priceLabel}</small>
                      <b>
                        {price}
                        {o.priceOnce && <em>{t("packNoSub")}</em>}
                      </b>
                      <span>
                        {t("packSplit", {
                          deposit: formatFcfa(splitPayment(o.price).deposit, locale),
                          balance: formatFcfa(splitPayment(o.price).balance, locale),
                        })}
                      </span>
                    </div>
                    <div className="pkp">
                      <small>{t("packNext")}</small>
                      <ul className="pkx">
                        {(t.raw("packNextItems") as string[]).map((z) => (
                          <li key={z}>
                            <i>✓</i>
                            {z}
                          </li>
                        ))}
                      </ul>
                    </div>
                    <a className="b b-w" href={buy} target="_blank" rel="noopener noreferrer">
                      <ProtoIcon name="wa" />
                      {t("order")}
                    </a>
                    <p className="pkn">
                      {t("packQuote")}{" "}
                      <a href={whatsappLink(t("packQuoteMessage", { title: o.title }))} target="_blank" rel="noopener noreferrer">
                        {t("packQuoteLink")}
                      </a>
                      .
                    </p>
                  </div>
                </div>
              </section>
            )}

            {o.calendar.length > 0 && (
              <section>
                <h2>{t("calendarTitle")}</h2>
                <ol className="cal">
                  {o.calendar.map((c) => (
                    <li key={c.day + c.text}>
                      <span>{c.day}</span>
                      <b>{c.text}</b>
                    </li>
                  ))}
                </ol>
                <p className="dm" style={{ fontSize: "14px", marginTop: "12px" }}>
                  {t("calendarNote")}
                </p>
              </section>
            )}

            {o.clientNeeds && (
              <section>
                <h2>{t("needsTitle")}</h2>
                <div className="needbox">
                  <span className="oic">
                    <ProtoIcon name="ok" />
                  </span>
                  <p>{o.clientNeeds}</p>
                </div>
              </section>
            )}

            <section>
              <h2>{t("engTitle")}</h2>
              <div className="eng3">
                {(t.raw("eng") as [string, string][]).map(([b, s]) => (
                  <div key={b}>
                    <b>{b}</b>
                    <span>{s}</span>
                  </div>
                ))}
              </div>
            </section>

            <section>
              <h2>{t("faqTitle")}</h2>
              <div className="faq2" style={{ gridTemplateColumns: "1fr" }}>
                {faq.map(([q, a]) => (
                  <details key={q}>
                    <summary>{q}</summary>
                    <p>{a}</p>
                  </details>
                ))}
              </div>
            </section>

            {proof && (
              <section>
                <h2>{t("proofTitle")}</h2>
                <Link className="proof" href={{ pathname: "/realisations/[slug]", params: { slug: proof.slug } }}>
                  <div className="im">
                    <ProjectImage project={proof} index={0} sizes="320px" />
                  </div>
                  <div>
                    <span className="tag">{tp(`status.${proof.status}`)}</span>
                    <h3>{proof.title}</h3>
                    {/* Résultat en une phrase (prototype PX.result) ; à défaut, extrait de la description. */}
                    <p className="mut">{proof.info?.result ?? getExcerpt(proof.description, 160)}</p>
                    <span className="lnk">
                      {t("proofSee")} <ProtoIcon name="ar" />
                    </span>
                  </div>
                </Link>
              </section>
            )}
          </div>

          <aside className="aside">
            <div className="card buy">
              <p className="dm" style={{ fontSize: "13.5px" }}>
                {priceLabel}
              </p>
              <p className="bp">{price}</p>
              <p style={{ color: "#BFE8CF", fontSize: "15px", marginTop: "4px" }}>{tl("readyIn", { delay: o.delay })}</p>
              <a className="b b-w" style={{ width: "100%", marginTop: "22px" }} href={buy} target="_blank" rel="noopener noreferrer">
                <ProtoIcon name="wa" />
                {t("order")}
              </a>
              <Link className="b b-g" style={{ width: "100%", marginTop: "10px" }} href={{ pathname: "/contact", query: { offre: o.slug } }}>
                {t("quote")}
              </Link>
              <p className="dm" style={{ fontSize: "13px", marginTop: "14px" }}>
                {t("buyNote")}
              </p>
            </div>
          </aside>
        </div>
      </section>

      {others.length > 0 && (
        <section className="sec tint">
          <div className="w">
            <div className="sh">
              <span className="kick">{t("otherKick")}</span>
              <h2>{t("otherTitle")}</h2>
            </div>
            <div className="ocat o3c">
              {others.map((z) => (
                <OfferCatCard key={z.id} offer={z} />
              ))}
            </div>
          </div>
        </section>
      )}

      <ProtoCta locale={locale} title={t("ctaTitle")} text={t("ctaText", { title: o.title })} />

      <div className="mbar">
        <span>
          <small>{priceLabel}</small>
          <b>{price}</b>
        </span>
        <a className="b b-w sm" href={buy} target="_blank" rel="noopener noreferrer">
          <ProtoIcon name="wa" />
          {t("orderShort")}
        </a>
      </div>
    </>
  );
}
