import { getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";
import { Badge, ButtonLink, Card, HeroBackground, Reveal } from "@/components/ui";
import { ProjectCard } from "@/components/sections/ProjectCard";
import { ArticleCard } from "@/components/sections/ArticleCard";
import { TestimonialCard } from "@/components/sections/TestimonialCard";
import { RoleRotator } from "@/components/sections/RoleRotator";
import { SkillsByCategory } from "@/components/sections/SkillsByCategory";
import {
  DevicesIcon,
  DualLensIcon,
  PaletteIcon,
  ShieldIcon,
  SparkleChatIcon,
} from "@/components/sections/AboutIcons";
import { getProjects } from "@/lib/api/projects";
import { getArticles } from "@/lib/api/articles";
import { getTestimonials } from "@/lib/api/testimonials";
import { getFeaturedSkills } from "@/lib/api/skills";
import { getHomeContent } from "@/lib/api/home-content";
import { getAboutContent } from "@/lib/api/about-content";

export const dynamic = "force-static";

function CheckIcon() {
  return (
    <svg
      aria-hidden="true"
      viewBox="0 0 20 20"
      width="18"
      height="18"
      className="mt-0.5 shrink-0 text-white"
    >
      <circle cx="10" cy="10" r="9" fill="currentColor" opacity="0.18" />
      <path
        d="M6 10.2l2.5 2.5L14 7.5"
        fill="none"
        stroke="currentColor"
        strokeWidth="1.8"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}

function CompassIcon() {
  return (
    <svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20">
      <circle cx="12" cy="12" r="8.5" fill="none" stroke="currentColor" strokeWidth="1.5" />
      <path d="M14.5 9.5 13 13l-3.5 1.5L11 11l3.5-1.5Z" fill="currentColor" />
    </svg>
  );
}

function TargetIcon() {
  return (
    <svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20">
      <circle cx="12" cy="12" r="8.5" fill="none" stroke="currentColor" strokeWidth="1.5" />
      <circle cx="12" cy="12" r="4.5" fill="none" stroke="currentColor" strokeWidth="1.5" />
      <circle cx="12" cy="12" r="1.3" fill="currentColor" />
    </svg>
  );
}

function MiniStat({ num, label }: { num: string; label: string }) {
  return (
    <div>
      <div
        className="text-xl font-extrabold text-brand-dark"
        style={{ fontFamily: "var(--font-heading)" }}
      >
        {num}
      </div>
      <div className="mt-0.5 font-mono text-[0.68rem] uppercase tracking-wide text-[var(--color-muted)]">
        {label}
      </div>
    </div>
  );
}

function BriefcaseIcon() {
  return (
    <svg aria-hidden="true" viewBox="0 0 20 20" width="14" height="14" className="shrink-0">
      <rect x="2.5" y="6" width="15" height="10" rx="1.5" fill="none" stroke="currentColor" strokeWidth="1.4" />
      <path d="M7 6V4.5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1V6" fill="none" stroke="currentColor" strokeWidth="1.4" />
      <path d="M2.5 10h15" stroke="currentColor" strokeWidth="1.4" />
    </svg>
  );
}

function getInitials(name: string): string {
  return name
    .split(" ")
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join("");
}

function ArchitectureDiagram({ caption }: { caption: string }) {
  return (
    <div>
      <svg viewBox="0 0 420 380" width="100%" role="img" aria-label={caption}>
        <defs>
          <marker id="archArrow" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="6" markerHeight="6" orient="auto">
            <path d="M0,0 L10,5 L0,10 z" fill="var(--color-brand-accent)" />
          </marker>
        </defs>
        <rect x="30" y="30" width="120" height="60" rx="10" fill="var(--color-bg-default)" stroke="var(--border-soft)" strokeWidth="1.5" />
        <text x="90" y="56" textAnchor="middle" fontSize="12" fontWeight="700" fill="var(--color-brand-dark)">Symfony</text>
        <text x="90" y="74" textAnchor="middle" fontSize="9" fill="var(--color-brand-primary)">Backend admin</text>

        <rect x="270" y="30" width="120" height="60" rx="10" fill="var(--color-bg-default)" stroke="var(--border-soft)" strokeWidth="1.5" />
        <text x="330" y="56" textAnchor="middle" fontSize="12" fontWeight="700" fill="var(--color-brand-dark)">Next.js</text>
        <text x="330" y="74" textAnchor="middle" fontSize="9" fill="var(--color-brand-primary)">Frontend public</text>

        <rect x="150" y="150" width="120" height="60" rx="10" fill="none" stroke="var(--color-brand-accent)" strokeWidth="2" className="arch-pulse-ring" />
        <rect x="150" y="150" width="120" height="60" rx="10" fill="var(--color-bg-default)" stroke="var(--color-brand-accent)" strokeWidth="2" />
        <text x="210" y="176" textAnchor="middle" fontSize="12" fontWeight="700" fill="var(--color-brand-dark)">API Platform</text>
        <text x="210" y="194" textAnchor="middle" fontSize="9" fill="var(--color-brand-primary)">Couche API</text>

        <rect x="150" y="270" width="120" height="60" rx="10" fill="var(--color-bg-default)" stroke="var(--border-soft)" strokeWidth="1.5" />
        <text x="210" y="296" textAnchor="middle" fontSize="12" fontWeight="700" fill="var(--color-brand-dark)">Assistant IA</text>
        <text x="210" y="314" textAnchor="middle" fontSize="9" fill="var(--color-brand-primary)">Profil · Qualification</text>

        {/* Symfony alimente l'API ; l'API dispatche ensuite les données au frontend et à l'IA — le sens des flèches suit ce flux réel, pas une simple symétrie visuelle. */}
        <path d="M90 90 C90 150, 150 150, 150 180" fill="none" stroke="var(--border-soft)" strokeWidth="1.4" />
        <path d="M270 180 C270 150, 330 150, 330 90" fill="none" stroke="var(--border-soft)" strokeWidth="1.4" />
        <path d="M210 210 L210 270" fill="none" stroke="var(--border-soft)" strokeWidth="1.4" />

        <path d="M90 90 C90 150, 150 150, 150 180" fill="none" stroke="var(--color-brand-accent)" strokeWidth="1.6" opacity="0.6" className="arch-flow" markerEnd="url(#archArrow)" style={{ animationDelay: "0s" }} />
        <path d="M270 180 C270 150, 330 150, 330 90" fill="none" stroke="var(--color-brand-accent)" strokeWidth="1.6" opacity="0.6" className="arch-flow" markerEnd="url(#archArrow)" style={{ animationDelay: "0.3s" }} />
        <path d="M210 210 L210 270" fill="none" stroke="var(--color-brand-accent)" strokeWidth="1.6" opacity="0.6" className="arch-flow" markerEnd="url(#archArrow)" style={{ animationDelay: "0.6s" }} />
      </svg>
      <p className="mt-3 text-center font-mono text-xs text-[var(--color-muted)]">{caption}</p>
    </div>
  );
}

export default async function HomePage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: "home" });
  const tc = await getTranslations({ locale, namespace: "common" });

  const [content, aboutContent, projects, articles, testimonials, skills] = await Promise.all([
    getHomeContent(locale),
    getAboutContent(locale),
    getProjects(locale),
    getArticles(locale),
    getTestimonials(locale),
    getFeaturedSkills(locale),
  ]);

  // Contenu narratif piloté par le back-office (App\Entity\HomeContent) : la
  // ligne unique est créée à la demande côté API, une absence signale donc
  // une vraie panne plutôt qu'un état normal à dégrader — cf. error.tsx.
  if (!content || !aboutContent) {
    throw new Error("Contenu de la page d'accueil indisponible.");
  }

  return (
    <>
      <section className="relative overflow-hidden px-6 pt-16 pb-20">
        <HeroBackground />

        <div className="relative mx-auto grid max-w-[1120px] gap-12 md:grid-cols-[1.05fr_0.95fr] md:items-center">
          <div>
            <div
              className="hero-in mb-4 flex flex-wrap items-center gap-x-4 gap-y-2"
              style={{ animationDelay: "0s" }}
            >
              <Badge variant="accent">
                <RoleRotator roles={content.heroRoles} />
              </Badge>
              <span className="inline-flex items-center gap-1.5 text-sm font-medium text-[var(--color-muted)]">
                <BriefcaseIcon />
                {content.founderBadge}
              </span>
            </div>
            {/* Pas d'animation d'entrée ici (contrairement au reste du hero) : ce
                <h1> est le candidat LCP le plus probable de la page — Chrome ne
                finalise la mesure LCP d'un élément qu'une fois son animation
                d'opacité stabilisée, un fondu ajouterait donc directement son
                délai+durée au LCP mesuré. */}
            <h1 className="mb-5 text-[clamp(1.75rem,3vw,2.75rem)] leading-[1.25]">
              {content.heroTitle}
              <br />
              <span className="text-brand-primary">{content.heroTitleAccent}</span>
            </h1>
            <p
              className="hero-in mb-7 max-w-[48ch] text-[1.05rem] opacity-75"
              style={{ animationDelay: "0.16s" }}
            >
              {content.heroSub}
            </p>
            <div
              className="hero-in mb-7 flex flex-wrap items-center gap-x-6 gap-y-3"
              style={{ animationDelay: "0.24s" }}
            >
              <ButtonLink href="/contact">{tc("ctaConfierProjet")}</ButtonLink>
              <a
                href="#freelance"
                className="text-sm font-semibold text-brand-primary hover:underline"
              >
                {t("ctaFreelance")} →
              </a>
            </div>
            <div
              className="hero-in flex flex-wrap gap-2.5"
              style={{ animationDelay: "0.32s" }}
            >
              <Badge variant="accent">Symfony</Badge>
              <Badge variant="accent">API Platform</Badge>
              <Badge variant="accent">Next.js</Badge>
              <Badge variant="outline">Assistant IA</Badge>
              <Badge variant="outline">Cybersécurité</Badge>
            </div>
          </div>
          <Card variant="soft" className="hero-in p-6" style={{ animationDelay: "0.16s" }}>
            <ArchitectureDiagram caption={content.diagramCaption} />
          </Card>
        </div>

        <div className="relative mx-auto mt-16 max-w-3xl">
          <Card
            variant="soft"
            className="hero-in p-6 sm:p-8"
            style={{ animationDelay: "0.4s" }}
          >
            <div className="flex flex-wrap items-center justify-center gap-x-10 gap-y-5 text-center sm:gap-x-14">
              <MiniStat num="10" label={t("stats.experience")} />
              <span aria-hidden="true" className="hidden h-10 w-px bg-[var(--border-softer)] sm:block" />
              <MiniStat num="15+" label={t("stats.projects")} />
              <span aria-hidden="true" className="hidden h-10 w-px bg-[var(--border-softer)] sm:block" />
              <MiniStat num={t("stats.open")} label={t("stats.freelance")} />
            </div>
            {testimonials[0] && (
              <div className="mt-6 flex flex-col items-center gap-3 border-t border-[var(--border-softer)] pt-6 text-center">
                <div
                  className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-bold text-white"
                  style={{
                    fontFamily: "var(--font-heading)",
                    background:
                      "linear-gradient(135deg, var(--cta-gradient-from), var(--cta-gradient-to))",
                  }}
                >
                  {getInitials(testimonials[0].author)}
                </div>
                <p className="max-w-[52ch] text-sm italic opacity-80">
                  &ldquo;{testimonials[0].content}&rdquo;
                </p>
                <p className="text-xs font-semibold uppercase tracking-wide opacity-60">
                  {testimonials[0].author}
                </p>
              </div>
            )}
          </Card>
        </div>
      </section>

      <section className="border-y border-[var(--border-softer)] bg-bg-card px-6 py-20">
        <div className="mx-auto grid max-w-[1120px] gap-12 lg:grid-cols-[0.9fr_1.1fr] lg:items-center">
          <Reveal delay={0}>
            <Badge className="mb-3.5">{t("about.eyebrow")}</Badge>
            <h2 className="mb-4 text-[clamp(1.75rem,3.5vw,2.5rem)]">{content.aboutTitle}</h2>
          </Reveal>
          <Reveal delay={0.08}>
            <p className="mb-4 opacity-75">{content.aboutP1}</p>
            <p className="opacity-75">{content.aboutP2}</p>
          </Reveal>
        </div>

        <div className="mx-auto mt-16 grid max-w-[1120px] grid-cols-1 gap-6 sm:grid-cols-2">
          <Reveal delay={0.12}>
            <Card variant="soft" className="p-6">
              <div className="mb-3 flex items-center gap-2 text-brand-primary">
                <CompassIcon />
                <span className="font-mono text-xs font-semibold uppercase tracking-wide">
                  {t("about.visionLabel")}
                </span>
              </div>
              <p className="text-[0.95rem] opacity-80">{content.aboutVisionText}</p>
            </Card>
          </Reveal>
          <Reveal delay={0.24}>
            <Card variant="soft" className="p-6">
              <div className="mb-3 flex items-center gap-2 text-brand-primary">
                <TargetIcon />
                <span className="font-mono text-xs font-semibold uppercase tracking-wide">
                  {t("about.missionLabel")}
                </span>
              </div>
              <p className="text-[0.95rem] opacity-80">{content.aboutMissionText}</p>
            </Card>
          </Reveal>
        </div>

        <div className="mx-auto mt-16 max-w-[1120px]">
          <dl className="grid grid-cols-1 gap-x-8 gap-y-10 sm:grid-cols-2">
            {[
              { icon: <DevicesIcon />, title: content.aboutHighlightTitle, desc: content.aboutHighlightDesc },
              { icon: <DualLensIcon />, title: aboutContent.why1Title, desc: aboutContent.why1Desc },
              { icon: <ShieldIcon />, title: aboutContent.why2Title, desc: aboutContent.why2Desc },
              { icon: <SparkleChatIcon />, title: aboutContent.why3Title, desc: aboutContent.why3Desc },
              { icon: <PaletteIcon />, title: aboutContent.why4Title, desc: aboutContent.why4Desc },
            ].map((item, i) => (
              <Reveal key={item.title} delay={i * 0.08} className="relative pl-16">
                <dt className="text-base font-semibold">
                  <div
                    className="absolute top-0 left-0 flex h-10 w-10 items-center justify-center rounded-lg text-white"
                    style={{
                      background:
                        "linear-gradient(135deg, var(--cta-gradient-from), var(--cta-gradient-to))",
                    }}
                  >
                    {item.icon}
                  </div>
                  {item.title}
                </dt>
                <dd className="mt-2 text-sm opacity-70">{item.desc}</dd>
              </Reveal>
            ))}
          </dl>
        </div>

        <div className="mx-auto mt-16 max-w-[1120px] text-center">
          <Reveal delay={0.16}>
            <Link
              href="/a-propos"
              className="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-primary hover:underline"
            >
              {t("about.cta")} →
            </Link>
          </Reveal>
        </div>
      </section>

      <section className="px-6 py-20">
        <div className="mx-auto max-w-[1120px]">
          <Reveal delay={0}>
            <Badge className="mb-3.5">{t("skills.eyebrow")}</Badge>
            <h2 className="mb-2 text-[clamp(1.75rem,3.5vw,2.5rem)]">{t("skills.title")}</h2>
            <p className="mb-10 max-w-[60ch] opacity-70">{t("skills.lede")}</p>
          </Reveal>
          {skills.length > 0 ? (
            <div className="mb-8">
              <SkillsByCategory skills={skills} maxSkillsPerCategory={4} />
            </div>
          ) : (
            <p className="mb-8 text-sm opacity-60">{t("skills.empty")}</p>
          )}
          <Reveal delay={0.1}>
            <Link
              href="/competences"
              className="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-primary hover:underline"
            >
              {t("skills.cta")} →
            </Link>
          </Reveal>
        </div>
      </section>

      <section className="px-6 py-20">
        <div className="mx-auto max-w-[1120px]">
          <Badge className="mb-3.5">{t("projects.eyebrow")}</Badge>
          <h2 className="mb-2 text-[clamp(1.75rem,3.5vw,2.5rem)]">{t("projects.title")}</h2>
          <p className="mb-10 max-w-[60ch] opacity-70">{t("projects.lede")}</p>
          {projects.length > 0 ? (
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
              {projects[0] && (
                <Reveal delay={0} className="lg:row-span-2">
                  <ProjectCard project={projects[0]} className="h-full" featured />
                </Reveal>
              )}
              <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-1">
                {projects.slice(1, 3).map((project, i) => (
                  <Reveal key={project.id} delay={(i + 1) * 0.08}>
                    <ProjectCard project={project} />
                  </Reveal>
                ))}
              </div>
            </div>
          ) : (
            <p className="text-sm opacity-60">{t("projects.empty")}</p>
          )}
          <div className="mt-8">
            <Link
              href="/realisations"
              className="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-primary hover:underline"
            >
              {t("projects.cta")} →
            </Link>
          </div>
        </div>
      </section>

      <section className="border-y border-[var(--border-softer)] bg-bg-card px-6 py-20">
        <div className="mx-auto max-w-[1120px] text-center">
          <Badge className="mb-3.5">{t("testimonials.eyebrow")}</Badge>
          <h2 className="mx-auto mb-10 max-w-[40ch] text-[clamp(1.75rem,3.5vw,2.5rem)]">
            {t("testimonials.title")}
          </h2>
          {testimonials.length > 0 ? (
            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
              {testimonials.slice(0, 3).map((testimonial, i) => (
                <Reveal
                  key={testimonial.id}
                  delay={i * 0.08}
                  className={i === 1 ? "lg:-translate-y-3" : undefined}
                >
                  <TestimonialCard
                    testimonial={testimonial}
                    className={i === 1 ? "shadow-md" : undefined}
                  />
                </Reveal>
              ))}
            </div>
          ) : (
            <p className="text-sm opacity-60">{t("testimonials.empty")}</p>
          )}
        </div>
      </section>

      <section className="px-6 py-20">
        <div className="mx-auto max-w-[1120px]">
          <Badge className="mb-3.5">{t("blog.eyebrow")}</Badge>
          <h2 className="mb-2 text-[clamp(1.75rem,3.5vw,2.5rem)]">{t("blog.title")}</h2>
          <p className="mb-10 max-w-[60ch] opacity-70">{t("blog.lede")}</p>
          {articles.length > 0 ? (
            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
              {articles.slice(0, 3).map((article, i) => (
                <Reveal key={article.id} delay={i * 0.08}>
                  <ArticleCard article={article} />
                </Reveal>
              ))}
            </div>
          ) : (
            <p className="text-sm opacity-60">{t("blog.empty")}</p>
          )}
        </div>
      </section>

      <section
        id="freelance"
        className="px-6 py-20 text-white"
        style={{
          background:
            "linear-gradient(135deg, var(--cta-gradient-from), var(--cta-gradient-to) 80%)",
        }}
      >
        <div className="mx-auto grid max-w-[1120px] gap-12 lg:grid-cols-[1.1fr_0.9fr] lg:items-center">
          <div>
            <Badge className="mb-3.5 bg-white/15 text-white">{t("freelance.eyebrow")}</Badge>
            <h2 className="mb-3 text-[clamp(1.75rem,3.5vw,2.5rem)] text-white">
              {content.freelanceTitle}
            </h2>
            <p className="mb-6 max-w-[60ch] opacity-85">{content.freelanceLede}</p>
            <ul className="list-none space-y-3 p-0 text-sm">
              <li className="flex items-start gap-2.5">
                <CheckIcon />
                {content.freelancePoint1}
              </li>
              <li className="flex items-start gap-2.5">
                <CheckIcon />
                {content.freelancePoint2}
              </li>
              <li className="flex items-start gap-2.5">
                <CheckIcon />
                {content.freelancePoint3}
              </li>
            </ul>
          </div>
          <Card className="p-7">
            <Badge variant="accent" className="mb-3">
              {tc("ctaConfierProjet")}
            </Badge>
            <h3 className="mb-2 text-xl font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
              {t("freelance.cardTitle")}
            </h3>
            <p className="mb-5 text-sm opacity-70">{content.freelanceCardDesc}</p>
            <ButtonLink href="/freelances" className="w-full">
              {t("freelance.cardCta")}
            </ButtonLink>
          </Card>
        </div>
      </section>

      <section className="px-6 py-20">
        <div className="card mx-auto max-w-[1120px] py-12 text-center">
          <h2 className="mb-3 text-[clamp(1.75rem,3.5vw,2.5rem)]">{content.contactCtaTitle}</h2>
          <p className="mx-auto mb-7 max-w-[56ch] opacity-70">{content.contactCtaSub}</p>
          <ButtonLink href="/contact">{tc("ctaConfierProjet")}</ButtonLink>
        </div>
      </section>
    </>
  );
}
