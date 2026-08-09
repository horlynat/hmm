import { getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";
import { Badge, ButtonLink, Card, HeroBackground, Reveal } from "@/components/ui";
import { ProjectsGrid } from "@/components/sections/ProjectsGrid";
import { getProjects } from "@/lib/api/projects";

export const dynamic = "force-static";

export default async function ProjectsPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: "projects" });
  const tc = await getTranslations({ locale, namespace: "common" });
  const projects = await getProjects(locale);

  return (
    <>
      <section className="relative overflow-hidden px-6 pt-16 pb-20">
        <HeroBackground />
        <div className="relative mx-auto max-w-[1120px]">
          <Badge variant="accent" className="hero-in mb-4" style={{ animationDelay: "0s" }}>
            {t("eyebrow")}
          </Badge>
          {/* Pas d'animation ici : candidat LCP le plus probable de la page. */}
          <h1 className="mb-5 text-[clamp(1.75rem,3vw,2.75rem)] leading-[1.25]">
            {t("title")} <span className="text-brand-primary">{t("titleAccent")}</span>
          </h1>
          <p
            className="hero-in mb-7 max-w-[60ch] text-[1.05rem] opacity-75"
            style={{ animationDelay: "0.16s" }}
          >
            {t("sub")}
          </p>
          <div
            className="hero-in mb-7 flex flex-wrap items-center gap-x-6 gap-y-3"
            style={{ animationDelay: "0.24s" }}
          >
            <ButtonLink href="/contact">{tc("ctaConfierProjet")}</ButtonLink>
            <a
              href="#case-study"
              className="text-sm font-semibold text-brand-primary hover:underline"
            >
              {t("caseStudy.eyebrow")} →
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
      </section>

      <section id="case-study" className="px-6 py-16">
        <div className="mx-auto max-w-[1120px]">
          <Reveal delay={0}>
            <Badge className="mb-3.5">{t("caseStudy.eyebrow")}</Badge>
            <h2 className="mb-2 text-[clamp(1.75rem,3.5vw,2.5rem)]">{t("caseStudy.title")}</h2>
            <p className="mb-8 max-w-[60ch] opacity-70">{t("caseStudy.lede")}</p>
          </Reveal>
          <Reveal delay={0.1}>
            <Card variant="soft" className="grid overflow-hidden p-0 md:grid-cols-2">
              <div
                className="flex flex-col gap-3 p-8 text-white"
                style={{
                  background:
                    "linear-gradient(135deg, var(--cta-gradient-from), var(--cta-gradient-to) 80%)",
                }}
              >
                <Badge className="w-fit bg-white/20 text-white">{t("caseStudy.badge")}</Badge>
                <h3
                  className="text-xl font-semibold text-white"
                  style={{ fontFamily: "var(--font-heading)" }}
                >
                  {t("caseStudy.cardTitle")}
                </h3>
                <p className="text-sm opacity-85">{t("caseStudy.cardText")}</p>
                <div className="mt-2 flex flex-wrap gap-2 text-xs">
                  {["Symfony", "API Platform", "Next.js", "Assistant IA"].map((n) => (
                    <div
                      key={n}
                      className="flex-1 rounded-lg border border-white/25 bg-white/10 py-2.5 text-center"
                    >
                      {n}
                    </div>
                  ))}
                </div>
              </div>
              <div className="p-8">
                <h3 className="mb-3 text-lg font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
                  {t("caseStudy.bodyTitle")}
                </h3>
                <p className="mb-3 text-sm opacity-75">{t("caseStudy.bodyText1")}</p>
                <p className="mb-5 text-sm opacity-75">{t("caseStudy.bodyText2")}</p>
                <div className="mb-5 flex flex-wrap gap-1.5">
                  {["Symfony", "API Platform", "Next.js", "Assistant IA", "Tailwind CSS v4"].map((n) => (
                    <Badge key={n} variant="outline">
                      {n}
                    </Badge>
                  ))}
                </div>
                <ButtonLink href="/a-propos" variant="secondary">
                  {t("caseStudy.linkText")}
                </ButtonLink>
              </div>
            </Card>
          </Reveal>
        </div>
      </section>

      <section className="border-y border-[var(--border-softer)] bg-bg-card px-6 py-16">
        <div className="mx-auto max-w-[1120px]">
          <Reveal delay={0}>
            <Badge className="mb-3.5">{t("list.eyebrow")}</Badge>
            <h2 className="mb-2 text-[clamp(1.75rem,3.5vw,2.5rem)]">{t("list.title")}</h2>
            <p className="mb-8 max-w-[60ch] opacity-70">{t("list.lede")}</p>
          </Reveal>
          <ProjectsGrid projects={projects} />
        </div>
      </section>

      <section className="px-6 py-16">
        <Reveal delay={0}>
          <Card
            variant="soft"
            className="mx-auto flex max-w-[1120px] flex-col items-center gap-3 border-dashed py-12 text-center"
          >
            <h3 className="max-w-[36ch] text-xl font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
              {t("invite.title")}
            </h3>
            <p className="mx-auto max-w-[52ch] text-sm opacity-70">{t("invite.text")}</p>
            <ButtonLink href="/contact" variant="secondary" className="mt-2">
              {t("invite.cta")}
            </ButtonLink>
          </Card>
        </Reveal>
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
            <h2 className="mb-3 text-[clamp(1.75rem,3.5vw,2.5rem)] text-white">{t("cta.title")}</h2>
            <p className="mx-auto mb-7 max-w-[56ch] opacity-85">{t("cta.sub")}</p>
            <div className="flex flex-wrap items-center justify-center gap-x-6 gap-y-3">
              <ButtonLink href="/contact" style={{ background: "#fff", color: "var(--color-on-brand-light)" }}>
                {tc("ctaConfierProjet")}
              </ButtonLink>
              <Link
                href="/competences"
                className="text-sm font-semibold text-white opacity-90 hover:underline hover:opacity-100"
              >
                {t("cta.linkText")} →
              </Link>
            </div>
          </Reveal>
        </div>
      </section>
    </>
  );
}
