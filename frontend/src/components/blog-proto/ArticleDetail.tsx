import Image from "next/image";
import { getTranslations } from "next-intl/server";
import { Link, getPathname } from "@/i18n/navigation";
import type { Article } from "@/lib/types";
import { getAboutContent } from "@/lib/api/about-content";
import { getArticleExcerpt, getReadingTimeMinutes, sanitizeArticleHtml } from "@/lib/sanitize";
import { splitArticleSections } from "@/lib/articleToc";
import { whatsappLink } from "@/lib/whatsapp";
import { siteConfig } from "@/config/site";
import { SITE_URL } from "@/lib/metadata";
import { PersonAvatarArt, ProtoIcon } from "@/components/proto/ProtoSvg";
import type { CSSProperties } from "react";
import { BlogCover } from "./BlogCover";
import { articleLabel, articleVisual } from "./blogThemes";
import { ArticleProgress } from "./ArticleProgress";
import { ArticleToc } from "./ArticleToc";
import { AskAssistantRow } from "./AskAssistantRow";

/**
 * Fiche d'un article — markup du prototype (`.artp2 .arh .arhg .agrid
 * .amain .abody2 .ase .aend .aauth2 .ashare2 .aside .atoc .aq .amore`),
 * alimentée par l'article du back-office : sommaire et ancres dérivés des
 * vrais titres H2 du contenu, date et temps de lecture réels. Le bloc
 * « offre liée » du prototype (`.aoff`) n'a pas d'équivalent dans le modèle
 * de données (Article ne porte aucune relation vers Offer) : plutôt que de
 * deviner un lien par rapprochement de texte, il n'est pas affiché — à
 * ajouter si un champ dédié est un jour créé côté back-office.
 */
const dateFormatter = (locale: string) => new Intl.DateTimeFormat(locale === "en" ? "en-GB" : "fr-FR", { dateStyle: "long" });

export async function ArticleDetail({
  locale,
  article,
  articles,
  publishedAt,
}: {
  locale: string;
  article: Article;
  articles: Article[];
  /** ISO 8601, quand le back-office l'expose ; sinon aucune date n'est affichée (pas de date inventée). */
  publishedAt: string | null;
}) {
  const [t, tc, tf, about] = await Promise.all([
    getTranslations({ locale, namespace: "blogPage" }),
    getTranslations({ locale, namespace: "catalog" }),
    getTranslations({ locale, namespace: "landing.footer" }),
    getAboutContent(locale),
  ]);

  const minutes = getReadingTimeMinutes(article.content);
  const { introHtml, sections } = splitArticleSections(sanitizeArticleHtml(article.content));
  const articleUrl = `${SITE_URL}${getPathname({ locale, href: { pathname: "/blog/[slug]", params: { slug: article.slug } } })}`;

  const others = articles.filter((a) => a.slug !== article.slug).slice(0, 3);
  // Comme le prototype : la dernière section devient « À retenir » (`.akey`)
  // dès que l'article en compte plus de deux.
  const keyIndex = sections.length > 2 ? sections.length - 1 : -1;

  return (
    <>
      <ArticleProgress />
      <article className="artp2" style={{ "--h": articleVisual(article)[1] } as CSSProperties}>
        <header className="arh">
          <div className="w arhg">
            <div className="arht">
              <p className="crumb">
                <Link href="/">{tc("crumbHome")}</Link> / <Link href="/blog">{t("crumb")}</Link>
                {article.tags.length > 0 && ` / ${articleLabel(article)}`}
              </p>
              {article.tags.length > 0 && <span className="achip">{articleLabel(article)}</span>}
              <h1>{article.title}</h1>
              <p className="ald2">{getArticleExcerpt(article.content, 220)}</p>
              <div className="ameta2">
                <span className="avs">
                  {about?.profileImagePath ? (
                    <Image src={about.profileImagePath} alt="" fill sizes="40px" style={{ objectFit: "cover" }} />
                  ) : (
                    <PersonAvatarArt />
                  )}
                </span>
                <span className="amn">
                  {/* Prénom seul dans l'en-tête, nom complet dans la carte auteur (prototype). */}
                  <b>{(about?.profileName ?? siteConfig.name).split(" ")[0]}</b>
                  {about?.profileRole && <small>{about.profileRole}</small>}
                </span>
                {publishedAt && (
                  <span className="amf">
                    <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                      <rect x="3.5" y="5" width="17" height="15" rx="3" />
                      <path d="M8 3v4M16 3v4M3.5 10h17" />
                    </svg>
                    <time dateTime={publishedAt}>{dateFormatter(locale).format(new Date(publishedAt))}</time>
                  </span>
                )}
                <span className="amf">
                  <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <circle cx="12" cy="12" r="9" />
                    <path d="M12 7v5l3 2" />
                  </svg>
                  {t("readingTime", { minutes })}
                </span>
              </div>
            </div>
            <div className="arcov">
              <BlogCover article={article} large alignLeft sizes="(max-width: 900px) 100vw, 560px" />
            </div>
          </div>
        </header>

        <div className="w agrid">
          <div className="amain">
            <div className="abody2" id="article-body">
              {introHtml && <div className="ase" dangerouslySetInnerHTML={{ __html: introHtml }} />}
              {sections.map((s, i) => (
                <section key={s.id} className={i === keyIndex ? "ase akey" : "ase"} id={s.id}>
                  {i === keyIndex && <span className="kk">{t("keyTakeaway")}</span>}
                  <h2>{s.heading}</h2>
                  {/* HTML assaini côté serveur (sanitizeArticleHtml) avant injection. */}
                  <div dangerouslySetInnerHTML={{ __html: s.bodyHtml }} />
                </section>
              ))}
            </div>

            <div className="aend">
              <div className="aauth2">
                <span className="avs lgv">
                  {about?.profileImagePath ? (
                    <Image src={about.profileImagePath} alt="" fill sizes="56px" style={{ objectFit: "cover" }} />
                  ) : (
                    <PersonAvatarArt />
                  )}
                </span>
                <div>
                  <span className="kk">{t("writtenBy")}</span>
                  <b>{about?.profileName ?? siteConfig.name}</b>
                  <p>{tf("tagline")}</p>
                </div>
                <Link className="b b-g sm" href="/a-propos">
                  {t("myPath")}
                </Link>
              </div>
              <div className="ashare2">
                <span>{t("shareTitle")}</span>
                <a className="b b-w sm" href={whatsappLink(`${article.title} ${articleUrl}`)} target="_blank" rel="noopener noreferrer">
                  <ProtoIcon name="wa" size={17} />
                  {t("shareWhatsapp")}
                </a>
                <a
                  className="b b-g sm"
                  href={`https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(articleUrl)}`}
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  {t("shareLinkedin")}
                </a>
              </div>
            </div>
          </div>

          <aside className="aside" aria-label={t("toc")}>
            <ArticleToc sections={sections} minutes={minutes} />
            <div className="aq">
              <b>{t("askSideTitle")}</b>
              <p>{t("askSideText")}</p>
              <AskAssistantRow variant="aside" message={t("askArticleMessage", { title: article.title })} />
            </div>
          </aside>
        </div>

        {others.length > 0 && (
          <section className="amore">
            <div className="w">
              <div className="amh">
                <h2>{t("readAlso")}</h2>
                <Link href="/blog">
                  {t("allArticles")} <ProtoIcon name="ar" />
                </Link>
              </div>
              <div className={others.length === 1 ? "bgrid solo" : "bgrid"}>
                {others.map((a) => (
                  <Link key={a.id} className="bcard" href={{ pathname: "/blog/[slug]", params: { slug: a.slug } }}>
                    <BlogCover article={a} sizes="(max-width: 900px) 100vw, 420px" />
                    <div>
                      {a.tags.length > 0 && <span className="dom">{a.tags.map((tg) => tg.name).join(" · ")}</span>}
                      <h3>{a.title}</h3>
                      <p>{getArticleExcerpt(a.content, 140)}</p>
                    </div>
                  </Link>
                ))}
              </div>
            </div>
          </section>
        )}
      </article>
    </>
  );
}
