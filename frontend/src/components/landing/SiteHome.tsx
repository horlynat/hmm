import Image from "next/image";
import { getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";
import { getOffers, heroOffers } from "@/lib/api/offers";
import { getProjects } from "@/lib/api/projects";
import { getAboutContent } from "@/lib/api/about-content";
import { sortProjectsForDisplay } from "@/lib/projects/sortForDisplay";
import { formatFcfa } from "@/lib/format/fcfa";
import { getMediaUrl } from "@/lib/media";
import { getExcerpt } from "@/lib/text";
import { whatsappLink } from "@/lib/whatsapp";
import type { Project } from "@/lib/types";
import {
  PROTO_PROJECT_ART,
  type ProtoIconName,
} from "@/components/proto/assets";
import { offerIcon, pickHomeOffers } from "./offerHelpers";
import {
  ProjectArt,
  ProtoIcon,
  SilhouetteArt,
  TrustIcon,
} from "@/components/proto/ProtoSvg";
import { LandingHero } from "./LandingHero";
import { HomeDemo } from "./HomeDemo";

/**
 * Accueil du site — markup et classes du prototype de refonte (copie
 * conforme : src/components/protopages/home.tsx), alimenté par le
 * back-office : carrousel et grille d'offres (Admin › Contenu › Offres),
 * réalisations (projets publics), portrait et repères (À propos). Les textes
 * fixes viennent des traductions (landing.*), FR et EN.
 */
const ART_KEYS = Object.keys(
  PROTO_PROJECT_ART,
) as (keyof typeof PROTO_PROJECT_ART)[];

/** Image de couverture du projet, sinon illustration du prototype (par slug, sinon par rang). */
export function ProjectImage({
  project,
  index,
  sizes,
}: {
  project: Project;
  index: number;
  sizes: string;
}) {
  const cover = project.info?.coverImage;
  if (cover) {
    return (
      <Image
        src={getMediaUrl(cover.filePath)}
        alt={cover.altText ?? ""}
        fill
        sizes={sizes}
        style={{ objectFit: "cover" }}
      />
    );
  }
  const key = Object.hasOwn(PROTO_PROJECT_ART, project.slug)
    ? (project.slug as keyof typeof PROTO_PROJECT_ART)
    : ART_KEYS[index % ART_KEYS.length];
  return <ProjectArt name={key} />;
}

/** Bloc « projet principal + projets secondaires » du prototype (projBlock : `.pjf` + `.pjs`). */
export async function ProjectsShowcase({
  locale,
  projects,
}: {
  locale: string;
  projects: Project[];
}) {
  const t = await getTranslations({ locale, namespace: "landing" });
  const [lead, ...others] = projects;
  if (!lead) return null;
  const projectStatus = (p: Project) => t(`projects.status.${p.status}`);
  return (
    <>
      <Link
        className="pjf"
        href={{ pathname: "/realisations/[slug]", params: { slug: lead.slug } }}
      >
        <div className="im">
          <ProjectImage
            project={lead}
            index={0}
            sizes="(max-width: 900px) 100vw, 600px"
          />
        </div>
        <div className="bd">
          <span className="tag" style={{ alignSelf: "flex-start" }}>
            {projectStatus(lead)}
          </span>
          <h3 style={{ fontSize: "26px", lineHeight: "1.25" }}>{lead.title}</h3>
          <p className="mut">{getExcerpt(lead.description, 240)}</p>
          <span className="lnk">
            {t("projects.see")} <ProtoIcon name="ar" />
          </span>
        </div>
      </Link>
      {others.length > 0 && (
        <div className="pjs">
          {others.map((p, i) => (
            <Link
              key={p.id}
              className="pj"
              href={{
                pathname: "/realisations/[slug]",
                params: { slug: p.slug },
              }}
            >
              <div className="im">
                <ProjectImage
                  project={p}
                  index={i + 1}
                  sizes="(max-width: 900px) 100vw, 560px"
                />
              </div>
              <div className="bd">
                <span className="tag" style={{ alignSelf: "flex-start" }}>
                  {projectStatus(p)}
                </span>
                <h3 style={{ fontSize: "20px" }}>{p.title}</h3>
                <p className="mut" style={{ fontSize: "15px" }}>
                  {getExcerpt(p.description, 180)}
                </p>
                <span className="lnk" style={{ marginTop: "auto" }}>
                  {t("projects.see")} <ProtoIcon name="ar" />
                </span>
              </div>
            </Link>
          ))}
        </div>
      )}
    </>
  );
}

export async function SiteHome({ locale }: { locale: string }) {
  const [t, offers, projects, about] = await Promise.all([
    getTranslations({ locale, namespace: "landing" }),
    getOffers(locale),
    getProjects(locale),
    getAboutContent(locale),
  ]);

  const carousel = heroOffers(offers);
  const homeOffers = pickHomeOffers(offers);
  const featured = offers.find((o) => o.featured) ?? null;
  const shown = sortProjectsForDisplay(projects).slice(0, 3);
  const lead = shown[0];
  const needs = (
    t.raw("needs.items") as {
      dom: string;
      title: string;
      text: string;
      offer: string;
      icon: ProtoIconName;
    }[]
  ).map((n) => ({
    ...n,
    // Lien vers l'offre ciblée seulement si elle est publiée ; sinon le catalogue.
    published: offers.some((o) => o.slug === n.offer),
  }));
  // Repères propres à l'accueil (différents de ceux de la page À propos, cf. prototype).
  const stats = t.raw("me.stats") as [string, string][];

  return (
    <>
      <LandingHero offers={carousel} />

      <section className="trust" aria-label={t("trust.label")}>
        <div className="w">
          {(t.raw("trust.items") as string[]).map((item, i) => (
            <span key={item}>
              <TrustIcon index={i} />
              {item}
            </span>
          ))}
        </div>
      </section>

      <section className="sec">
        <div className="w">
          <div className="sh c">
            <span className="kick">{t("needs.kick")}</span>
            <h2>{t("needs.title")}</h2>
            <p>{t("needs.lede")}</p>
          </div>
          <div className="need">
            {needs.map((n) => (
              <Link
                key={n.offer}
                href={
                  n.published
                    ? { pathname: "/offres/[slug]", params: { slug: n.offer } }
                    : "/offres"
                }
              >
                <span className="ic">
                  <ProtoIcon name={n.icon} />
                </span>
                <span className="ntx">
                  <small className="dom">{n.dom}</small>
                  <b>{n.title}</b>
                </span>
                <p>{n.text}</p>
                <span>
                  {t("needs.cta")} <ProtoIcon name="ar" />
                </span>
              </Link>
            ))}
          </div>
        </div>
      </section>

      {homeOffers.length > 0 && (
        <section className="sec tint">
          <div className="w">
            <div className="sh">
              <span className="kick">{t("offers.kick")}</span>
              <h2>{t("offers.title")}</h2>
              <p>{t("offers.lede")}</p>
            </div>
            <div className={`offers o${Math.min(homeOffers.length, 4)}`}>
              {homeOffers.map((o) => {
                const href = {
                  pathname: "/offres/[slug]" as const,
                  params: { slug: o.slug },
                };
                return (
                  <article key={o.id} className={o.featured ? "of ft" : "of "}>
                    <div className="top">
                      <span className="oic">
                        <ProtoIcon name={offerIcon(o.icon)} />
                      </span>
                      {o.featured && (
                        <span className="tag">{t("offers.featuredTag")}</span>
                      )}
                    </div>
                    <div className="bd">
                      <h3>
                        <Link href={href} className="olink">
                          {o.title}
                        </Link>
                      </h3>
                      <p>{o.cardText}</p>
                      <ul>
                        {o.includes.slice(0, 3).map((item) => (
                          <li key={item}>
                            <ProtoIcon name="ok" />
                            {item}
                          </li>
                        ))}
                      </ul>
                      <div className="ft2">
                        <div className="pr">
                          <small>
                            {o.priceOnce
                              ? t("hero.priceOnce")
                              : t("hero.priceFrom")}
                          </small>
                          <b>{formatFcfa(o.price, locale)}</b>
                        </div>
                        <span className="dl">
                          {t("hero.readyIn", { delay: o.delay })}
                        </span>
                      </div>
                    </div>
                    <div className="act">
                      {o.featured && (
                        <a
                          className="b b-w sm"
                          href={whatsappLink(
                            t("hero.orderMessage", { title: o.title }),
                          )}
                          target="_blank"
                          rel="noopener noreferrer"
                        >
                          <ProtoIcon name="wa" />
                          {t("offers.order")}
                        </a>
                      )}
                      <Link className="b b-g sm" href={href}>
                        {t("offers.detail")}
                      </Link>
                    </div>
                  </article>
                );
              })}
            </div>
            <p className="swipe">
              {t("offers.swipe")} <ProtoIcon name="ar" />
            </p>
            <div className="eng">
              {(t.raw("offers.eng") as [string, string][]).map(([b, s]) => (
                <div key={b}>
                  <b>{b}</b>
                  <span>{s}</span>
                </div>
              ))}
              <Link className="b b-g" href="/offres">
                {t("offers.all")}
              </Link>
            </div>
          </div>
        </section>
      )}

      <section className="sec showcase" id="demo">
        <div className="w">
          <div className="sh c">
            <span className="kick">{t("demo.kick")}</span>
            <h2>{t("demo.title")}</h2>
            <p>{t("demo.lede")}</p>
          </div>
          <div className="sc">
            <HomeDemo />
            <div className="scx">
              <ol className="mini">
                {(t.raw("demo.steps") as [string, string][]).map(([b, s]) => (
                  <li key={b}>
                    <b>{b}</b>
                    <span>{s}</span>
                  </li>
                ))}
              </ol>
              {featured && (
                <div className="scp">
                  <span className="p">
                    {featured.priceOnce
                      ? t("hero.priceOnce")
                      : t("hero.priceFrom")}{" "}
                    <b>{formatFcfa(featured.price, locale)}</b>
                  </span>
                  <span className="d">
                    <i />
                    {t("hero.readyIn", { delay: featured.delay })}
                  </span>
                </div>
              )}
              <div style={{ display: "flex", flexWrap: "wrap", gap: "12px" }}>
                <a
                  className="b b-w"
                  href={whatsappLink(t("demo.orderMessage"))}
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  <ProtoIcon name="wa" />
                  {t("demo.order")}
                </a>
                <Link
                  className="b b-g"
                  href={
                    featured
                      ? {
                          pathname: "/offres/[slug]",
                          params: { slug: featured.slug },
                        }
                      : "/offres"
                  }
                >
                  {t("demo.seeOffer")}
                </Link>
              </div>
            </div>
          </div>
        </div>
      </section>

      {lead && (
        <section className="sec">
          <div className="w">
            <div className="sh">
              <span className="kick">{t("projects.kick")}</span>
              <h2>{t("projects.title")}</h2>
              <p>{t("projects.lede")}</p>
            </div>
            <ProjectsShowcase locale={locale} projects={shown} />
            <div style={{ textAlign: "center", marginTop: "36px" }}>
              <Link
                className="b b-g"
                href={{ pathname: "/a-propos", hash: "realisations" }}
              >
                {t("projects.all")}
              </Link>
            </div>
          </div>
        </section>
      )}

      <section className="sec tint">
        <div className="w meh">
          <figure className="portrait ">
            <div className="pfr">
              <div className="pph">
                {about?.profileImagePath ? (
                  <Image
                    src={about.profileImagePath}
                    alt={about.profileName || t("me.name")}
                    fill
                    sizes="340px"
                    style={{ objectFit: "cover" }}
                  />
                ) : (
                  <SilhouetteArt />
                )}
              </div>
              <span className="pbadge">
                <i />
                {about?.profileAvailability || t("me.available")}
              </span>
            </div>
            <figcaption>
              <b>{t("me.name")}</b>
              <span>{about?.profileRole || t("me.role")}</span>
            </figcaption>
          </figure>
          <div>
            <span className="kick">{t("me.kick")}</span>
            <h2 className="h2s" style={{ margin: "16px 0" }}>
              {t("me.title")}
            </h2>
            <p className="mut" style={{ fontSize: "17px", maxWidth: "52ch" }}>
              {t("me.text")}
            </p>
            <div className="pstat sm">
              {stats.map(([b, s]) => (
                <div key={b + s}>
                  <b>{b}</b>
                  <span>{s}</span>
                </div>
              ))}
            </div>
            <div className="row4" style={{ marginTop: "26px" }}>
              <Link className="b b-g" href="/a-propos">
                {t("me.more")}
              </Link>
              <a
                className="b b-w"
                href={whatsappLink(t("cta.message"))}
                target="_blank"
                rel="noopener noreferrer"
              >
                <ProtoIcon name="wa" />
                {t("me.whatsapp")}
              </a>
            </div>
          </div>
        </div>
      </section>

      <section className="sec" id="methode">
        <div className="w">
          <div className="sh c">
            <span className="kick">{t("steps.kick")}</span>
            <h2>{t("steps.title")}</h2>
            <p>{t("steps.lede")}</p>
          </div>
          <ol className="steps6">
            {(t.raw("steps.items") as [string, string][]).map(([b, p], i) => (
              <li key={b}>
                <span className="n">{i + 1}</span>
                <b>{b}</b>
                <p>{p}</p>
              </li>
            ))}
          </ol>
        </div>
      </section>

      <section className="sec tint" id="faq">
        <div className="w">
          <div className="sh c">
            <span className="kick">{t("faq.kick")}</span>
            <h2>{t("faq.title")}</h2>
          </div>
          <div className="faq2">
            {(t.raw("faq.items") as [string, string][]).map(([q, a], i) => (
              <details key={q}>
                <summary>{q}</summary>
                <p>
                  {a}
                  {i === 3 && (
                    <>
                      {" "}
                      <Link
                        className="lnk"
                        href="/politique-de-confidentialite"
                      >
                        {t("faq.privacyLink")}
                      </Link>
                    </>
                  )}
                </p>
              </details>
            ))}
          </div>
          <p className="faqm">
            {t("faq.more")}{" "}
            <a
              href={whatsappLink(t("faq.moreMessage"))}
              target="_blank"
              rel="noopener noreferrer"
            >
              {t("faq.moreLink")}
            </a>
          </p>
        </div>
      </section>

      <section className="sec" style={{ paddingTop: "40px" }}>
        <div className="w">
          <div className="cta">
            <h2>{t("cta.title")}</h2>
            <p>{t("cta.text")}</p>
            <div className="row">
              <a
                className="b b-w"
                href={whatsappLink(t("cta.message"))}
                target="_blank"
                rel="noopener noreferrer"
              >
                <ProtoIcon name="wa" />
                {t("cta.whatsapp")}
              </a>
              <Link className="b b-g" href="/offres">
                {t("cta.offers")}
              </Link>
            </div>
          </div>
        </div>
      </section>
    </>
  );
}
