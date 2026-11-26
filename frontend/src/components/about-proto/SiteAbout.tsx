import Image from "next/image";
import { getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";
import { getAboutContent } from "@/lib/api/about-content";
import { getCourses } from "@/lib/api/courses";
import { getExperiences } from "@/lib/api/experiences";
import { getOffers } from "@/lib/api/offers";
import { getProjects } from "@/lib/api/projects";
import { getSkillDomains } from "@/lib/api/skill-domains";
import { sortProjectsForDisplay } from "@/lib/projects/sortForDisplay";
import { getExcerpt } from "@/lib/text";
import { whatsappLink } from "@/lib/whatsapp";
import type { Course, Experience } from "@/lib/types";
import { ProtoIcon, SilhouetteArt } from "@/components/proto/ProtoSvg";
import { ProtoCta } from "@/components/proto/ProtoSections";
import { ProjectsShowcase } from "@/components/landing/SiteHome";
import { SkillTabs, type SkillDomain } from "./SkillTabs";

/**
 * Page À propos — markup du prototype (pAbout : `.ph.abh .abg .portrait.lg
 * .sub .vision .vm3 .hzn .ab .pts .skt #realisations .pstat .pgrid .ptl
 * .plearn`), alimentée par le back-office : textes (Admin › À propos,
 * FR/EN), expériences, formations, réalisations et offres publiées.
 * Tous les textes viennent du back-office (Contenu › Page « À propos » et
 * Domaines de compétence) ; seuls les petits libellés (« Ma vision »,
 * « Expérience »…) restent dans les traductions. Offres et preuve d'un
 * domaine ne sont liées que si elles sont publiées.
 */
const year = (iso: string) => new Date(iso).getUTCFullYear();

function experienceYears(e: Experience): string {
  const start = year(e.startDate);
  if (!e.endDate) return String(start);
  const end = year(e.endDate);
  return start === end ? String(start) : `${start} – ${end}`;
}

function courseYears(c: Course): string {
  const start = year(c.startDate);
  const end = year(c.endDate);
  return start === end ? String(start) : `${start} – ${end}`;
}

export async function SiteAbout({ locale }: { locale: string }) {
  const [t, tl, about, rawDomains, experiences, courses, offers, projects] = await Promise.all([
    getTranslations({ locale, namespace: "aboutPage" }),
    getTranslations({ locale, namespace: "catalog" }),
    getAboutContent(locale),
    getSkillDomains(locale),
    getExperiences(locale),
    getCourses(locale),
    getOffers(locale),
    getProjects(locale),
  ]);
  // `new Date()`, pas `Date.now()` (même convention que Footer.tsx pour
  // l'année courante) : page revalidée toutes les heures (cf. page.tsx),
  // donc cette lecture de l'heure reste fraîche en pratique.
  const now = new Date().getTime();
  const monthYear = new Intl.DateTimeFormat(locale === "en" ? "en-GB" : "fr-FR", { month: "long", year: "numeric" });

  // API injoignable (build ou régénération) : on refuse de produire une page
  // amputée. En régénération, Next garde alors la dernière bonne version au
  // lieu de mettre en cache une page vide (incident du 2026-11-26).
  if (about === null || rawDomains === null) {
    throw new Error("API À propos indisponible : page non régénérée.");
  }

  const domains: SkillDomain[] = rawDomains.map((d) => {
    const proof = d.proofProjectSlug ? projects.find((p) => p.slug === d.proofProjectSlug) : undefined;
    return {
      icon: d.icon,
      title: d.title,
      short: d.short,
      text: d.text,
      do: d.do,
      tools: d.tools,
      offers: d.offerSlugs.map((slug) => offers.find((o) => o.slug === slug)).filter((o) => o !== undefined),
      proof: proof ? { slug: proof.slug, title: proof.title } : null,
    };
  });

  const pillars = about ? [about.visionPillar, about.missionPillar, about.ambitionPillar] : [];
  const horizon = about ? [about.visionTodayText, about.horizonMidText, about.visionTomorrowText] : [];
  const why = about
    ? [
        [about.why1Title, about.why1Desc],
        [about.why2Title, about.why2Desc],
        [about.why3Title, about.why3Desc],
        [about.why4Title, about.why4Desc],
        // 5e point du prototype, facultatif au back-office (filtré si vide).
        [about.why5Title, about.why5Desc],
      ].filter(([b]) => b)
    : [];
  const sortedExperiences = [...experiences].sort((a, b) => b.startDate.localeCompare(a.startDate));
  const sortedCourses = [...courses].sort((a, b) => b.startDate.localeCompare(a.startDate));
  const sub = ["vision", "qui", "competences", "realisations", "parcours"] as const;

  return (
    <>
      <section className="ph abh">
        <div className="w abg">
          <div>
            <p className="crumb">
              <Link href="/">{tl("crumbHome")}</Link> / {t("crumb")}
            </p>
            {about && (
              <>
                <h1>
                  {about.heroTitle} <span className="grad">{about.heroTitleAccent}</span>
                </h1>
                <p>{about.heroSub}</p>
              </>
            )}
            <div className="row4" style={{ marginTop: "28px" }}>
              <a className="b b-w" href={whatsappLink(t("talkMessage"))} target="_blank" rel="noopener noreferrer">
                <ProtoIcon name="wa" />
                {t("talk")}
              </a>
              <Link className="b b-g" href={{ pathname: "/a-propos", hash: "realisations" }}>
                {t("seeProjects")}
              </Link>
            </div>
          </div>
          <figure className="portrait lg">
            <div className="pfr">
              <div className="pph">
                {about?.profileImagePath ? (
                  <Image src={about.profileImagePath} alt={about.profileName} fill sizes="380px" style={{ objectFit: "cover" }} />
                ) : (
                  <SilhouetteArt />
                )}
              </div>
              {about?.profileAvailability && (
                <span className="pbadge">
                  <i />
                  {about.profileAvailability}
                </span>
              )}
            </div>
            {about && (
              <figcaption>
                <b>{about.profileName}</b>
                <span>{about.profileRole}</span>
              </figcaption>
            )}
          </figure>
        </div>
      </section>

      <nav className="sub" aria-label={t("subLabel")}>
        <div className="w">
          {sub.map((id) => (
            <Link key={id} href={{ pathname: "/a-propos", hash: id }}>
              {t(`sub.${id}`)}
            </Link>
          ))}
        </div>
      </nav>

      {about && (
        <section className="vision" id="vision">
          <div className="w">
            <span className="kick">{t("visionKick")}</span>
            {about.visionLede && <p className="vq">« {about.visionLede} »</p>}
            {pillars.some(Boolean) && (
              <div className="vm3">
                {pillars.map((text, k) =>
                  text ? (
                    <div key={k}>
                      <span className="vn">0{k + 1}</span>
                      <h3>{(t.raw("pillars") as string[])[k]}</h3>
                      <p>{text}</p>
                    </div>
                  ) : null,
                )}
              </div>
            )}
            {horizon.some(Boolean) && (
              <div className="hzn">
                <p className="skl">{t("horizonTitle")}</p>
                <ol>
                  {horizon.map((text, k) =>
                    text ? (
                      <li key={k}>
                        <span>{(t.raw("horizon") as string[])[k]}</span>
                        <p>{text}</p>
                      </li>
                    ) : null,
                  )}
                </ol>
              </div>
            )}
          </div>
        </section>
      )}

      {about && (
        <section className="sec" id="qui">
          <div className="w ab">
            <div className="txt">
              <span className="kick">{t("quiKick")}</span>
              <h2 className="h2s" style={{ marginTop: "16px" }}>
                {about.bioTitle}
              </h2>
              {[about.bioP1, about.bioP2].filter(Boolean).map((p) => (
                <p key={p}>{p}</p>
              ))}
            </div>
            {why.length > 0 && (
              <div className="pts">
                {why.map(([b, s]) => (
                  <div key={b}>
                    <i>
                      <ProtoIcon name="ok" />
                    </i>
                    <b>{b}</b>
                    <span>{s}</span>
                  </div>
                ))}
              </div>
            )}
          </div>
        </section>
      )}

      {domains.length > 0 && (
        <section className="sec tint" id="competences">
          <div className="w">
            <div className="sh">
              <span className="kick">{t("skillsKick")}</span>
              {about.skillsTitle && <h2>{about.skillsTitle}</h2>}
              {about.skillsLede && <p>{about.skillsLede}</p>}
            </div>
            <SkillTabs domains={domains} note={about.skillsNote} />
          </div>
        </section>
      )}

      {projects.length > 0 && (
        <section className="sec" id="realisations">
          <div className="w">
            <div className="sh">
              <span className="kick">{t("projectsKick")}</span>
              {about.projectsTitle && <h2>{about.projectsTitle}</h2>}
              {about.projectsLede && <p>{about.projectsLede}</p>}
            </div>
            <ProjectsShowcase locale={locale} projects={sortProjectsForDisplay(projects)} />
          </div>
        </section>
      )}

      <section className="sec tint" id="parcours">
        <div className="w">
          <div className="sh">
            <span className="kick">{t("pathKick")}</span>
            {about.pathTitle && <h2>{about.pathTitle}</h2>}
            {about.pathLede && <p>{about.pathLede}</p>}
          </div>
          {about && about.journeyStats.length > 0 && (
            <div className="pstat">
              {about.journeyStats.map((s) => (
                <div key={s.value + s.label}>
                  <b>{s.value}</b>
                  <span>{s.label}</span>
                </div>
              ))}
            </div>
          )}
          <div className="pgrid">
            <div>
              <h3 className="pch">
                <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  <rect x="2" y="7" width="20" height="14" rx="2" />
                  <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2M2 13h20" />
                </svg>
                {t("experience")}
              </h3>
              <ol className="ptl">
                {sortedExperiences.map((e) => (
                  <li key={e.id} className={e.endDate ? "" : "now"}>
                    <span className="py">{experienceYears(e)}</span>
                    <div>
                      <b>{e.role}</b>
                      <small>{e.company}</small>
                      {e.description && <p>{getExcerpt(e.description, 180)}</p>}
                    </div>
                  </li>
                ))}
              </ol>
            </div>
            <div>
              <h3 className="pch">
                <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  <path d="M22 10 12 5 2 10l10 5 10-5z" />
                  <path d="M6 12v5c3 2 9 2 12 0v-5" />
                </svg>
                {t("education")}
              </h3>
              <ol className="ptl">
                {sortedCourses.map((c) => {
                  const upcoming = new Date(c.startDate).getTime() > now;
                  const details = [c.institution, c.description ? getExcerpt(c.description, 100) : ""].filter(Boolean).join(" · ");
                  return (
                    <li key={c.id} className={upcoming ? "next" : ""}>
                      <span className="py">
                        {upcoming ? t("from", { date: monthYear.format(new Date(c.startDate)) }) : courseYears(c)}
                        {upcoming && <span className="soon2">{t("soon")}</span>}
                      </span>
                      <div>
                        <b>{c.title}</b>
                        {details && <small>{details}</small>}
                      </div>
                    </li>
                  );
                })}
              </ol>
              {about?.learningText && (
                <div className="plearn">
                  <b>{t("learning")}</b>
                  <span>{about.learningText}</span>
                </div>
              )}
            </div>
          </div>
        </div>
      </section>

      <ProtoCta locale={locale} title={about?.ctaTitle || undefined} text={about?.ctaSub || undefined} />
    </>
  );
}
