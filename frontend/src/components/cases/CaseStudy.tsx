import Image from "next/image";
import { getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";
import type { Offer, Project } from "@/lib/types";
import { formatFcfa } from "@/lib/format/fcfa";
import { getMediaUrl } from "@/lib/media";
import { getExcerpt } from "@/lib/text";
import { sanitizeArticleHtml } from "@/lib/sanitize";
import { whatsappLink } from "@/lib/whatsapp";
import { ProtoIcon } from "@/components/proto/ProtoSvg";
import { ProtoCta } from "@/components/proto/ProtoSections";
import { ProjectImage } from "@/components/landing/SiteHome";

/**
 * Étude de cas d'une réalisation — markup du prototype (pCase : `.ph.cx .cxg
 * .res .device .brief .br3 .facts4 .story .chap .road .liv .gal .disc .chips
 * .same .nxp .mbar`), alimentée par la fiche projet du back-office
 * (ProjectInfo) : résultat, bref en trois temps, repères (rôle + résultats
 * chiffrés), point de départ, démarche, livrables, galerie légendée, note
 * « projet discret », technologies. Chaque bloc sans donnée est masqué ; les
 * fiches antérieures au récit retombent sur description, objectifs et défis.
 */
export function httpUrl(value: string | null): string | null {
  if (!value) return null;
  try {
    const url = new URL(value);
    return url.protocol === "https:" || url.protocol === "http:" ? url.href : null;
  } catch {
    return null;
  }
}

export async function CaseStudy({
  locale,
  project: p,
  next,
  offer,
}: {
  locale: string;
  project: Project;
  next: Project | null;
  offer: Offer | null;
}) {
  const [t, tp, tc] = await Promise.all([
    getTranslations({ locale, namespace: "casePage" }),
    getTranslations({ locale, namespace: "landing.projects" }),
    getTranslations({ locale, namespace: "catalog" }),
  ]);
  const info = p.info;
  const facts = [
    ...(info?.role ? [{ label: t("role"), value: info.role }] : []),
    ...(info?.results ?? []).map((r) => ({ label: r.label, value: r.value ?? "" })),
  ].slice(0, 4);
  const steps: { title: string; text: string | null }[] =
    info && info.steps.length > 0 ? info.steps : (info?.objectives ?? []).map((o) => ({ title: o, text: null }));
  const deliverables = info && info.deliverables.length > 0 ? info.deliverables : (info?.challenges ?? []).map((c) => c.solution).filter(Boolean);
  const captions = info?.galleryCaptions ?? [];
  const stack = info?.techStack ?? [];
  const gallery = (p.media ?? []).filter((m) => m.type === "image" || (m.mimeType ?? "").startsWith("image/"));
  const html = sanitizeArticleHtml(p.description);
  // Liens saisis au back-office : seulement http(s), jamais javascript:/data:.
  const link = httpUrl(p.link);
  const repo = httpUrl(info?.repoUrl ?? null);

  return (
    <>
      <section className="ph cx">
        <div className="w">
          <p className="crumb">
            <Link href="/">{tc("crumbHome")}</Link> /{" "}
            <Link href={{ pathname: "/a-propos", hash: "realisations" }}>{t("crumb")}</Link>
          </p>
          <div className="cxg">
            <div>
              <span className="tag">{tp(`status.${p.status}`)}</span>
              <h1>{p.title}</h1>
              {info?.result && <p className="res">{info.result}</p>}
              <p>{getExcerpt(p.description, 320)}</p>
              {(link || repo) && (
                <div style={{ display: "flex", gap: "12px", flexWrap: "wrap", marginTop: "20px" }}>
                  {link && (
                    <a className="b b-g sm" href={link} target="_blank" rel="noopener noreferrer">
                      {t("visit")}
                    </a>
                  )}
                  {repo && (
                    <a className="b b-g sm" href={repo} target="_blank" rel="noopener noreferrer">
                      {t("code")}
                    </a>
                  )}
                </div>
              )}
            </div>
            <div className="device">
              <ProjectImage project={p} index={0} sizes="(max-width: 900px) 100vw, 640px" />
            </div>
          </div>
        </div>
      </section>

      {(facts.length > 0 || (info?.brief.length ?? 0) > 0) && (
        <section className="brief">
          <div className="w">
            {info && info.brief.length > 0 && (
              <div className="br3">
                {info.brief.slice(0, 3).map((b, k) => (
                  <div key={k}>
                    <span className="n">{k + 1}</span>
                    <b>{b.title}</b>
                    {b.text && <p>{b.text}</p>}
                  </div>
                ))}
              </div>
            )}
            {facts.length > 0 && (
              <dl className="facts4" style={info && info.brief.length > 0 ? undefined : { marginTop: 0 }}>
                {facts.map((f) => (
                  <div key={f.label + f.value}>
                    <dt>{f.label}</dt>
                    <dd>{f.value}</dd>
                  </div>
                ))}
              </dl>
            )}
          </div>
        </section>
      )}

      <section className="sec">
        <div className="w story">
          {info?.context || info?.stake ? (
            <div className="chap">
              <span className="kick">{t("startKick")}</span>
              <h2>{t("startTitle")}</h2>
              {info.context && <p className="t">{info.context}</p>}
              {info.stake && <p className="t">{info.stake}</p>}
            </div>
          ) : (
            html && (
              <div className="chap">
                <span className="kick">{t("startKick")}</span>
                <h2>{t("startTitle")}</h2>
                {/* HTML du back-office, assaini (liste blanche) avant rendu. */}
                <div className="t-rich" dangerouslySetInnerHTML={{ __html: html }} />
              </div>
            )
          )}

          {steps.length > 0 && (
            <div className="chap">
              <span className="kick">{t("stepsKick")}</span>
              <h2>{t("stepsTitle")}</h2>
              <ol className="road">
                {steps.map((s, k) => (
                  <li key={k}>
                    <b>{s.title}</b>
                    {s.text && <p>{s.text}</p>}
                  </li>
                ))}
              </ol>
            </div>
          )}

          {(info?.solution || deliverables.length > 0) && (
            <div className="chap">
              <span className="kick">{t("resultKick")}</span>
              <h2>{t("resultTitle")}</h2>
              {info?.solution && <p className="t">{info.solution}</p>}
              {deliverables.length > 0 && (
                <div className="liv">
                  {deliverables.map((s, k) => (
                    <div key={k}>
                      <ProtoIcon name="ok" />
                      <span>{s}</span>
                    </div>
                  ))}
                </div>
              )}
            </div>
          )}

          {captions.length > 0 ? (
            // Galerie légendée (prototype) : image du projet si elle existe, sinon
            // l'illustration du projet pour la première et « Capture réelle à
            // venir » pour les suivantes.
            <div className="chap">
              <span className="kick">{t("galleryKick")}</span>
              <h2>{t("galleryTitle")}</h2>
              <div className="gal">
                {/* Autant d'emplacements que de légendes ou d'images : une image
                    sans légende reste affichée (légende = texte alternatif). */}
                {Array.from({ length: Math.max(captions.length, gallery.length) }, (_, k) => {
                  const m = gallery[k];
                  const caption = captions[k] ?? m?.altText ?? "";
                  return (
                    <figure key={k}>
                      {m ? (
                        <div className="gim">
                          <Image src={getMediaUrl(m.filePath)} alt={m.altText ?? ""} fill sizes="(max-width: 900px) 100vw, 520px" />
                        </div>
                      ) : k === 0 ? (
                        <div className="gim">
                          <ProjectImage project={p} index={0} sizes="(max-width: 900px) 100vw, 520px" />
                        </div>
                      ) : (
                        <div className="gim ph2">
                          <span>{t("galleryPending")}</span>
                        </div>
                      )}
                      {caption && <figcaption>{caption}</figcaption>}
                    </figure>
                  );
                })}
              </div>
            </div>
          ) : gallery.length > 0 && (
            <div className="chap">
              <span className="kick">{t("galleryKick")}</span>
              <h2>{t("galleryTitle")}</h2>
              <div className="gal">
                {gallery.slice(0, 3).map((m) => (
                  <figure key={m.id}>
                    <div className="gim">
                      <Image src={getMediaUrl(m.filePath)} alt={m.altText ?? ""} fill sizes="(max-width: 900px) 100vw, 520px" />
                    </div>
                    {m.altText && <figcaption>{m.altText}</figcaption>}
                  </figure>
                ))}
              </div>
            </div>
          )}

          {info?.discreet && (
            <div className="disc">
              <ProtoIcon name="lock" />
              <div>
                <b>{t("discreetTitle")}</b>
                <p>{t("discreetText")}</p>
              </div>
            </div>
          )}

          {stack.length > 0 && (
            <div className="chap">
              <span className="kick">{t("techKick")}</span>
              <div className="chips">
                {stack.map((s) => (
                  <span key={s.name} className="chip">
                    {s.name}
                  </span>
                ))}
              </div>
            </div>
          )}

          {offer && (
            <Link className="same" href={{ pathname: "/offres/[slug]", params: { slug: offer.slug } }}>
              <div>
                <span className="kick">{t("sameKick")}</span>
                <h2>{t("sameTitle")}</h2>
                <p>{t("sameText", { title: offer.title, price: formatFcfa(offer.price, locale) })}</p>
              </div>
              <span className="b b-p">
                {t("sameCta")} <ProtoIcon name="ar" />
              </span>
            </Link>
          )}

          {next && (
            <Link className="nxp" href={{ pathname: "/realisations/[slug]", params: { slug: next.slug } }}>
              <span>
                <small>{t("next")}</small>
                <b>{next.title}</b>
              </span>
              <ProtoIcon name="ar" />
            </Link>
          )}
        </div>
      </section>

      <ProtoCta locale={locale} title={t("ctaTitle")} text={t("ctaText")} />

      <div className="mbar">
        <span>
          <small>{t("mbarSmall")}</small>
          <b>{t("mbarBig")}</b>
        </span>
        <a className="b b-w sm" href={whatsappLink(t("mbarMessage", { title: p.title }))} target="_blank" rel="noopener noreferrer">
          <ProtoIcon name="wa" />
          WhatsApp
        </a>
      </div>
    </>
  );
}
