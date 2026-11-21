import { getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";
import { Badge, ButtonLink, Card, Reveal } from "@/components/ui";
import { ProjectCard } from "@/components/sections/ProjectCard";
import { ArticleCard } from "@/components/sections/ArticleCard";
import { TestimonialCard } from "@/components/sections/TestimonialCard";
import { SkillsByCategory } from "@/components/sections/SkillsByCategory";
import {
  DevicesIcon,
  DualLensIcon,
  PaletteIcon,
  ShieldIcon,
  SparkleChatIcon,
} from "@/components/sections/AboutIcons";
import { getProjects } from "@/lib/api/projects";
import { sortProjectsForDisplay } from "@/lib/projects/sortForDisplay";
import { getArticles } from "@/lib/api/articles";
import { getTestimonials } from "@/lib/api/testimonials";
import { getSkills } from "@/lib/api/skills";
import { getHomeContent } from "@/lib/api/home-content";
import { getAboutContent } from "@/lib/api/about-content";
import { HOME_FEATURED_SKILL_CATEGORIES } from "@/lib/skills/featuredCategories";
import { getOffers, heroOffers } from "@/lib/api/offers";
import { HomeHero } from "@/components/home/HomeHero";
import { TrustBar } from "@/components/home/TrustBar";
import { NeedsSection } from "@/components/home/NeedsSection";
import { OffersSection } from "@/components/home/OffersSection";
import { AiDemoSection } from "@/components/home/AiDemoSection";
import { getAiAssistantSettings } from "@/lib/api/ai-assistant";
import { StepsSection } from "@/components/home/StepsSection";
import { FaqSection } from "@/components/home/FaqSection";

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





export default async function HomePage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: "home" });
  const tc = await getTranslations({ locale, namespace: "common" });

  const [content, aboutContent, projects, articles, testimonials, skills, offers, aiAssistantSettings] = await Promise.all([
    getHomeContent(locale),
    getAboutContent(locale),
    getProjects(locale),
    getArticles(locale),
    getTestimonials(locale),
    getSkills(locale),
    getOffers(locale),
    // Même requête (et même cache) que MarketingLayout, qui ne monte le widget
    // IA que si ces réglages existent : sans eux, le bouton « Discuter avec
    // mon assistant IA » de la démo n'aurait rien à ouvrir.
    getAiAssistantSettings(locale),
  ]);

  // Contenu narratif piloté par le back-office (App\Entity\HomeContent) : la
  // ligne unique est créée à la demande côté API, une absence signale donc
  // une vraie panne plutôt qu'un état normal à dégrader — cf. error.tsx.
  if (!content || !aboutContent) {
    throw new Error("Contenu de la page d'accueil indisponible.");
  }

  // 6 derniers projets terminés, complétés par les projets en cours si moins
  // de 6 sont terminés (cf. sortProjectsForDisplay) — au-delà, on renvoie
  // vers /realisations plutôt que d'allonger la home indéfiniment.
  const homeProjects = sortProjectsForDisplay(projects).slice(0, 6);

  // Refonte 2026-11 : les offres (back-office, publiées seulement) pilotent le
  // hero et la grille. Sans offre publiée — état initial après déploiement,
  // les 7 offres de la migration étant en brouillon — le hero se replie sur
  // un message fixe et les sections « besoins »/« offres » sont masquées
  // plutôt que d'afficher une grille vide.
  const carouselOffers = heroOffers(offers);
  const assistantOffer = offers.find((offer) => offer.previewType === "chat") ?? null;

  return (
    <>
      <HomeHero locale={locale} offers={carouselOffers} />
      <TrustBar locale={locale} />
      {offers.length > 0 && (
        <>
          <NeedsSection locale={locale} />
          <OffersSection offers={offers} />
        </>
      )}

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

      <AiDemoSection locale={locale} offer={assistantOffer} assistantAvailable={aiAssistantSettings !== null} />

      <section className="px-6 py-20">
        <div className="mx-auto max-w-[1120px]">
          <Reveal delay={0}>
            <Badge className="mb-3.5">{t("skills.eyebrow")}</Badge>
            <h2 className="mb-2 text-[clamp(1.75rem,3.5vw,2.5rem)]">{t("skills.title")}</h2>
            <p className="mb-10 max-w-[60ch] opacity-70">{t("skills.lede")}</p>
          </Reveal>
          {skills.length > 0 ? (
            <div className="mb-8">
              <SkillsByCategory
                skills={skills}
                featuredCategories={HOME_FEATURED_SKILL_CATEGORIES}
                maxSkillsPerCategory={4}
              />
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
          {homeProjects.length > 0 ? (
            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
              {homeProjects.map((project, i) => (
                <Reveal key={project.id} delay={i * 0.08}>
                  <ProjectCard project={project} className="h-full" />
                </Reveal>
              ))}
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

      <StepsSection locale={locale} />

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

      <FaqSection locale={locale} />

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
