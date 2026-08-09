import { getTranslations } from "next-intl/server";
import Image from "next/image";
import { Badge, ButtonLink, Card, HeroBackground, Reveal } from "@/components/ui";
import { SkillChip } from "@/components/sections/SkillChip";
import { Timeline } from "@/components/sections/Timeline";
import {
  DualLensIcon,
  PaletteIcon,
  ShieldIcon,
  SparkleChatIcon,
} from "@/components/sections/AboutIcons";
import { getExperiences } from "@/lib/api/experiences";
import { getCourses } from "@/lib/api/courses";
import { getSkills } from "@/lib/api/skills";
import { getAboutContent } from "@/lib/api/about-content";

export const dynamic = "force-static";

export default async function AboutPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: "about" });
  const tc = await getTranslations({ locale, namespace: "common" });

  const [content, experiences, courses, skills] = await Promise.all([
    getAboutContent(locale),
    getExperiences(locale),
    getCourses(locale),
    getSkills(locale),
  ]);

  // Contenu narratif piloté par le back-office (App\Entity\AboutContent) : la
  // ligne unique est créée à la demande côté API, une absence signale donc
  // une vraie panne plutôt qu'un état normal à dégrader — cf. error.tsx.
  if (!content) {
    throw new Error('Contenu de la page "À propos" indisponible.');
  }

  const whyItems = [
    { icon: <DualLensIcon />, title: content.why1Title, desc: content.why1Desc },
    { icon: <ShieldIcon />, title: content.why2Title, desc: content.why2Desc },
    { icon: <SparkleChatIcon />, title: content.why3Title, desc: content.why3Desc },
    { icon: <PaletteIcon />, title: content.why4Title, desc: content.why4Desc },
  ];

  return (
    <>
      <section className="relative overflow-hidden px-6 pt-16 pb-20">
        <HeroBackground />

        <div className="relative mx-auto grid max-w-[1120px] gap-12 md:grid-cols-[1.05fr_0.95fr] md:items-center">
          <div>
            <Badge variant="accent" className="hero-in mb-4" style={{ animationDelay: "0s" }}>
              {content.heroEyebrow}
            </Badge>
            {/* Pas d'animation ici : candidat LCP le plus probable de la page. */}
            <h1 className="mb-5 text-[clamp(1.75rem,3vw,2.75rem)] leading-[1.25]">
              {content.heroTitle} <span className="text-brand-primary">{content.heroTitleAccent}</span>
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
                href="/cv-horlynat-mampassi-mbama.pdf"
                download
                className="text-sm font-semibold text-brand-primary hover:underline"
              >
                {tc("ctaTelechargerCv")} →
              </a>
            </div>
            <div className="hero-in flex flex-wrap gap-2.5" style={{ animationDelay: "0.32s" }}>
              <Badge variant="accent">Symfony</Badge>
              <Badge variant="accent">API Platform</Badge>
              <Badge variant="accent">Next.js</Badge>
              <Badge variant="outline">Assistant IA</Badge>
              <Badge variant="outline">Cybersécurité</Badge>
            </div>
          </div>
          <Card variant="soft" className="hero-in p-7 text-center" style={{ animationDelay: "0.16s" }}>
            <div
              className="mx-auto mb-4 flex h-[88px] w-[88px] items-center justify-center rounded-full text-2xl font-extrabold text-white"
              style={{
                fontFamily: "var(--font-heading)",
                background:
                  "linear-gradient(135deg, var(--cta-gradient-from), var(--cta-gradient-to))",
              }}
            >
              HM
            </div>
            <div className="mb-1 text-lg font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
              {content.profileName}
            </div>
            <div className="mb-5 text-sm opacity-65">{content.profileRole}</div>
            <ul className="list-none space-y-2 border-t border-[var(--border-softer)] p-0 pt-4 text-left">
              <li className="flex justify-between text-sm">
                <span className="opacity-60">{t("card.dispo")}</span>
                <span className="font-semibold text-success">{content.profileAvailability}</span>
              </li>
              <li className="flex justify-between text-sm">
                <span className="opacity-60">{t("card.aussi")}</span>
                <span className="font-semibold">{content.profileAlso}</span>
              </li>
              <li className="flex justify-between text-sm">
                <span className="opacity-60">{t("card.localisation")}</span>
                <span className="font-semibold">{content.profileLocation}</span>
              </li>
              <li className="flex justify-between text-sm">
                <span className="opacity-60">{t("card.mode")}</span>
                <span className="font-semibold">{content.profileWorkMode}</span>
              </li>
              <li className="flex justify-between text-sm">
                <span className="opacity-60">{t("card.langues")}</span>
                <span className="font-semibold">{content.profileLanguages}</span>
              </li>
            </ul>
            <a href="/cv-horlynat-mampassi-mbama.pdf" download className="btn-secondary mt-5 w-full">
              {tc("ctaTelechargerCv")}
            </a>
          </Card>
        </div>
      </section>

      <section className="px-6 py-16">
        <div className="mx-auto grid max-w-[1120px] gap-12 md:grid-cols-2 md:items-center">
          <Reveal
            delay={0.16}
            className="relative h-full rounded-sm -mx-6 aspect-[2/3] w-[calc(100%+3rem)] overflow-hidden md:mx-auto md:w-full md:max-w-[380px] md:aspect-[4/5] md:rounded-[var(--radius-lg)] md:border md:border-[var(--border-soft)] md:shadow-lg"
          >
            <Image
              src="/images/portrait-horlynat.jpg"
              alt={content.profileName}
              fill
              sizes="(min-width: 768px) 380px, 100vw"
              className="object-top object-cover h-full w-full "
              priority
            />
          </Reveal>

          <div>
            <Reveal delay={0}>
              <Badge className="mb-3.5">{t("bio.eyebrow")}</Badge>
              <h2 className="mb-6 text-[clamp(1.75rem,3.5vw,2.5rem)]">{content.bioTitle}</h2>
            </Reveal>
            <Reveal delay={0.08} className="max-w-[60ch] space-y-4 opacity-78">
              <p>{content.bioP1}</p>
              <p>{content.bioP2}</p>
              <p>{content.bioP3}</p>
            </Reveal>
          </div>
        </div>
      </section>

      <section className="border-y border-[var(--border-softer)] bg-bg-card px-6 py-16">
        <div className="mx-auto max-w-[1120px]">
          <Reveal delay={0}>
            <Badge className="mb-3.5">{t("vision.eyebrow")}</Badge>
            <h2 className="mb-2 text-[clamp(1.75rem,3.5vw,2.5rem)]">{content.visionTitle}</h2>
            <p className="mb-10 max-w-[60ch] opacity-70">{content.visionLede}</p>
          </Reveal>
          <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
            <Reveal delay={0.12}>
              <Card variant="soft" className="p-6">
                <Badge variant="accent" className="mb-4">
                  {t("vision.today")}
                </Badge>
                <p className="text-sm opacity-75">{content.visionTodayText}</p>
              </Card>
            </Reveal>
            <Reveal delay={0.2}>
              <Card variant="soft" className="p-6">
                <Badge variant="outline" className="mb-4">
                  {t("vision.tomorrow")}
                </Badge>
                <p className="text-sm opacity-75">{content.visionTomorrowText}</p>
              </Card>
            </Reveal>
          </div>
        </div>
      </section>

      <section className="px-6 py-16">
        <div className="mx-auto grid max-w-[1120px] gap-12 md:grid-cols-2">
          <Reveal delay={0}>
            <Badge className="mb-3.5">{t("formation.eyebrow")}</Badge>
            <h2 className="mb-6 text-xl" style={{ fontFamily: "var(--font-heading)" }}>
              {t("formation.title")}
            </h2>
            {courses.length > 0 ? (
              <Timeline
                items={courses.map((c) => ({
                  title: `${c.title} — ${c.institution}`,
                  desc: c.description,
                }))}
              />
            ) : (
              <p className="text-sm opacity-60">{tc("aVenir")}</p>
            )}
          </Reveal>
          <Reveal delay={0.1}>
            <Badge className="mb-3.5">{t("experience.eyebrow")}</Badge>
            <h2 className="mb-6 text-xl" style={{ fontFamily: "var(--font-heading)" }}>
              {t("experience.title")}
            </h2>
            {experiences.length > 0 ? (
              <Timeline
                items={experiences.map((e) => ({
                  title: `${e.role} — ${e.company}`,
                  desc: e.description,
                }))}
              />
            ) : (
              <p className="text-sm opacity-60">{tc("aVenir")}</p>
            )}
          </Reveal>
        </div>
      </section>

      <section className="border-y border-[var(--border-softer)] bg-bg-card px-6 py-16">
        <div className="mx-auto max-w-[1120px]">
          <Reveal delay={0}>
            <Badge className="mb-3.5">{t("skillsDetail.eyebrow")}</Badge>
            <h2 className="mb-2 text-[clamp(1.75rem,3.5vw,2.5rem)]">{t("skillsDetail.title")}</h2>
            <p className="mb-10 max-w-[60ch] opacity-70">{t("skillsDetail.lede")}</p>
          </Reveal>
          {skills.length > 0 ? (
            <div className="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
              {skills.map((skill, i) => (
                <Reveal key={skill.id} delay={i * 0.05}>
                  <SkillChip skill={skill} />
                </Reveal>
              ))}
            </div>
          ) : (
            <p className="text-sm opacity-60">{tc("aVenir")}</p>
          )}
        </div>
      </section>

      <section className="px-6 py-16">
        <div className="mx-auto max-w-[1120px]">
          <Reveal delay={0}>
            <Badge className="mb-3.5">{t("why.eyebrow")}</Badge>
            <h2 className="mb-10 text-[clamp(1.75rem,3.5vw,2.5rem)]">{t("why.title")}</h2>
          </Reveal>
          <dl className="grid grid-cols-1 gap-x-8 gap-y-10 md:grid-cols-2">
            {whyItems.map((item, i) => (
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
      </section>

      <section className="border-y border-[var(--border-softer)] bg-bg-card px-6 py-16">
        <div className="mx-auto max-w-[1120px]">
          <Reveal delay={0}>
            <Badge className="mb-3.5">{t("beyond.eyebrow")}</Badge>
            <h2 className="mb-6 text-xl" style={{ fontFamily: "var(--font-heading)" }}>
              {t("beyond.title")}
            </h2>
          </Reveal>
          <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
            <Reveal delay={0.08}>
              <Card className="p-6">
                <div className="mb-2.5 font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
                  {t("beyond.langues")}
                </div>
                <div className="flex flex-wrap gap-1.5">
                  {content.beyondLanguages.map((langue) => (
                    <Badge key={langue} variant="outline">{langue}</Badge>
                  ))}
                </div>
              </Card>
            </Reveal>
            <Reveal delay={0.16}>
              <Card className="p-6">
                <div className="mb-2.5 font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
                  {t("beyond.interets")}
                </div>
                <div className="flex flex-wrap gap-1.5">
                  {content.beyondInterests.map((interet) => (
                    <Badge key={interet} variant="outline">{interet}</Badge>
                  ))}
                </div>
              </Card>
            </Reveal>
          </div>
        </div>
      </section>

      <section
        className="px-6 py-16 text-center text-white"
        style={{
          background:
            "linear-gradient(135deg, var(--cta-gradient-from), var(--cta-gradient-to) 80%)",
        }}
      >
        <div className="mx-auto max-w-[1120px]">
          <Reveal delay={0}>
            <h2 className="mb-3 text-[clamp(1.75rem,3.5vw,2.5rem)] text-white">{content.ctaTitle}</h2>
            <p className="mx-auto mb-7 max-w-[56ch] opacity-85">{content.ctaSub}</p>
            <ButtonLink href="/contact" style={{ background: "#fff", color: "var(--color-on-brand-light)" }}>
              {tc("ctaConfierProjet")}
            </ButtonLink>
          </Reveal>
        </div>
      </section>
    </>
  );
}
