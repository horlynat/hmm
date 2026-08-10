import type { ReactNode } from "react";
import { getTranslations } from "next-intl/server";
import { Badge, ButtonLink, Card, HeroBackground, Reveal } from "@/components/ui";
import { SkillsByCategory } from "@/components/sections/SkillsByCategory";
import { getSkills } from "@/lib/api/skills";

export const dynamic = "force-static";

function DocumentIcon() {
  return (
    <svg aria-hidden="true" viewBox="0 0 20 20" width="18" height="18">
      <path
        d="M5 2.5h7l3 3v12a.5.5 0 0 1-.5.5h-9.5a.5.5 0 0 1-.5-.5v-14a.5.5 0 0 1 .5-.5Z"
        fill="none"
        stroke="currentColor"
        strokeWidth="1.4"
        strokeLinejoin="round"
      />
      <path d="M12 2.5v3h3" fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinejoin="round" />
      <path d="M6.5 10h7M6.5 12.5h7M6.5 15h4.5" stroke="currentColor" strokeWidth="1.3" strokeLinecap="round" />
    </svg>
  );
}

function GridIcon() {
  return (
    <svg aria-hidden="true" viewBox="0 0 20 20" width="18" height="18">
      <rect x="3" y="3" width="14" height="14" rx="1.5" fill="none" stroke="currentColor" strokeWidth="1.4" />
      <path d="M3 8.3h14M3 13.6h14M8.3 3v14M13.6 3v14" stroke="currentColor" strokeWidth="1.2" />
    </svg>
  );
}

function SlideIcon() {
  return (
    <svg aria-hidden="true" viewBox="0 0 20 20" width="18" height="18">
      <rect x="2.5" y="4" width="15" height="11" rx="1.3" fill="none" stroke="currentColor" strokeWidth="1.4" />
      <path d="M8 8.2l4 2.3-4 2.3V8.2Z" fill="currentColor" />
    </svg>
  );
}

function EnvelopeIcon() {
  return (
    <svg aria-hidden="true" viewBox="0 0 20 20" width="18" height="18">
      <rect x="2.5" y="4.5" width="15" height="11" rx="1.5" fill="none" stroke="currentColor" strokeWidth="1.4" />
      <path
        d="M3 5.5l7 5.5 7-5.5"
        fill="none"
        stroke="currentColor"
        strokeWidth="1.4"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}

function NotebookIcon() {
  return (
    <svg aria-hidden="true" viewBox="0 0 20 20" width="18" height="18">
      <rect x="5" y="2.5" width="12" height="15" rx="1.3" fill="none" stroke="currentColor" strokeWidth="1.4" />
      <path d="M3 5h2M3 8h2M3 11h2M3 14h2" stroke="currentColor" strokeWidth="1.3" strokeLinecap="round" />
      <path d="M8.5 2.5v4l1.5-1.2 1.5 1.2v-4" fill="currentColor" />
    </svg>
  );
}

function DatabaseIcon() {
  return (
    <svg aria-hidden="true" viewBox="0 0 20 20" width="18" height="18">
      <ellipse cx="10" cy="4.5" rx="6.5" ry="2.2" fill="none" stroke="currentColor" strokeWidth="1.4" />
      <path
        d="M3.5 4.5v11c0 1.2 2.9 2.2 6.5 2.2s6.5-1 6.5-2.2v-11"
        fill="none"
        stroke="currentColor"
        strokeWidth="1.4"
      />
      <path d="M3.5 10c0 1.2 2.9 2.2 6.5 2.2s6.5-1 6.5-2.2" fill="none" stroke="currentColor" strokeWidth="1.4" />
    </svg>
  );
}

function LayoutIcon() {
  return (
    <svg aria-hidden="true" viewBox="0 0 20 20" width="18" height="18">
      <rect x="2.5" y="3" width="15" height="14" rx="1.3" fill="none" stroke="currentColor" strokeWidth="1.4" />
      <path d="M2.5 8h15M7.3 8v9" stroke="currentColor" strokeWidth="1.3" />
    </svg>
  );
}

function TeamIcon() {
  return (
    <svg aria-hidden="true" viewBox="0 0 20 20" width="18" height="18">
      <circle cx="7.5" cy="7" r="2.6" fill="none" stroke="currentColor" strokeWidth="1.4" />
      <circle cx="13" cy="8.3" r="2" fill="none" stroke="currentColor" strokeWidth="1.4" />
      <path
        d="M2.8 16.5c.5-3 2.4-4.6 4.7-4.6s4.2 1.6 4.7 4.6"
        fill="none"
        stroke="currentColor"
        strokeWidth="1.4"
        strokeLinecap="round"
      />
      <path d="M12.3 12.3c1.9.2 3.3 1.6 3.7 4" fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" />
    </svg>
  );
}

function CloudIcon() {
  return (
    <svg aria-hidden="true" viewBox="0 0 20 20" width="18" height="18">
      <path
        d="M6 15h8.5a3 3 0 0 0 .4-5.97 4.5 4.5 0 0 0-8.6-1.66A3.5 3.5 0 0 0 6 15Z"
        fill="none"
        stroke="currentColor"
        strokeWidth="1.4"
        strokeLinejoin="round"
      />
    </svg>
  );
}

function ShareIcon() {
  return (
    <svg aria-hidden="true" viewBox="0 0 20 20" width="18" height="18">
      <circle cx="15" cy="4.5" r="2" fill="none" stroke="currentColor" strokeWidth="1.4" />
      <circle cx="15" cy="15.5" r="2" fill="none" stroke="currentColor" strokeWidth="1.4" />
      <circle cx="5" cy="10" r="2" fill="none" stroke="currentColor" strokeWidth="1.4" />
      <path d="M6.7 9l6.6-3.6M6.7 11l6.6 3.6" stroke="currentColor" strokeWidth="1.4" />
    </svg>
  );
}

const TOOL_ICONS: Record<string, ReactNode> = {
  Word: <DocumentIcon />,
  Excel: <GridIcon />,
  PowerPoint: <SlideIcon />,
  Outlook: <EnvelopeIcon />,
  OneNote: <NotebookIcon />,
  Access: <DatabaseIcon />,
  Publisher: <LayoutIcon />,
  Teams: <TeamIcon />,
  OneDrive: <CloudIcon />,
  SharePoint: <ShareIcon />,
};

function CheckIcon() {
  return (
    <svg aria-hidden="true" viewBox="0 0 20 20" width="18" height="18" className="shrink-0 text-success">
      <circle cx="10" cy="10" r="9" fill="currentColor" opacity="0.15" />
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

export default async function SkillsPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: "skills" });
  const tc = await getTranslations({ locale, namespace: "common" });
  const skills = await getSkills(locale);
  const softItems = t.raw("soft.items") as string[];
  const toolItems = t.raw("tools.items") as string[];

  return (
    <>
      <section className="relative overflow-hidden px-6 pt-16 pb-20">
        <HeroBackground />
        <div className="relative mx-auto max-w-[1120px]">
          <Badge variant="accent" className="hero-in mb-4" style={{ animationDelay: "0s" }}>
            {t("eyebrow")}
          </Badge>
          {/* Pas d'animation ici : candidat LCP le plus probable de la page. */}
          <h1 className="mb-5 max-w-[22ch] text-[clamp(1.75rem,3vw,2.75rem)] leading-[1.25]">
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
      </section>

      <section className="border-y border-[var(--border-softer)] bg-bg-card px-6 py-16">
        <div className="mx-auto max-w-[1120px]">
          <Reveal delay={0}>
            <Badge className="mb-3.5">{t("list.eyebrow")}</Badge>
            <h2 className="mb-2 text-[clamp(1.75rem,3.5vw,2.5rem)]">{t("list.title")}</h2>
            <p className="mb-10 max-w-[60ch] opacity-70">{t("list.lede")}</p>
          </Reveal>
          {skills.length > 0 ? (
            <SkillsByCategory skills={skills} />
          ) : (
            <p className="text-sm opacity-60">{t("list.empty")}</p>
          )}
        </div>
      </section>

      <section
        className="px-6 py-16 text-white"
        style={{
          background:
            "linear-gradient(135deg, var(--cta-gradient-from), var(--cta-gradient-to) 80%)",
        }}
      >
        <div className="mx-auto max-w-[1120px]">
          <Reveal delay={0}>
            <Badge className="mb-3.5 bg-white/15 text-white">{t("domain.eyebrow")}</Badge>
            <h2 className="mb-2 text-[clamp(1.75rem,3.5vw,2.5rem)] text-white">
              {t("domain.title")}
            </h2>
            <p className="mb-10 max-w-[60ch] opacity-85">{t("domain.lede")}</p>
          </Reveal>
          <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
            <Reveal delay={0.1}>
              <div className="rounded-[var(--radius-md)] border border-white/20 bg-white/8 p-6">
                <Badge className="mb-3 bg-white/20 text-white">{t("domain.cyberTitle")}</Badge>
                <p className="text-sm opacity-90">{t("domain.cyberText")}</p>
              </div>
            </Reveal>
            <Reveal delay={0.18}>
              <div className="rounded-[var(--radius-md)] border border-white/20 bg-white/8 p-6">
                <Badge className="mb-3 bg-white/20 text-white">{t("domain.assuranceTitle")}</Badge>
                <p className="text-sm opacity-90">{t("domain.assuranceText")}</p>
              </div>
            </Reveal>
          </div>
        </div>
      </section>

      <section className="px-6 py-16">
        <div className="mx-auto max-w-[1120px]">
          <Reveal delay={0}>
            <Badge className="mb-3.5">{t("soft.eyebrow")}</Badge>
            <h2 className="mb-2 text-[clamp(1.75rem,3.5vw,2.5rem)]">{t("soft.title")}</h2>
            <p className="mb-8 max-w-[60ch] opacity-70">{t("soft.lede")}</p>
          </Reveal>
          <div className="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
            {softItems.map((item, i) => (
              <Reveal key={item} delay={i * 0.04}>
                <Card className="flex items-center gap-3 px-4 py-3">
                  <CheckIcon />
                  <span className="text-sm font-medium">{item}</span>
                </Card>
              </Reveal>
            ))}
          </div>
        </div>
      </section>

      <section className="border-y border-[var(--border-softer)] bg-bg-card px-6 py-16">
        <div className="mx-auto max-w-[1120px]">
          <Reveal delay={0}>
            <Badge className="mb-3.5">{t("tools.eyebrow")}</Badge>
            <h2 className="mb-8 text-[clamp(1.75rem,3.5vw,2.5rem)]">{t("tools.title")}</h2>
          </Reveal>
          <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
            {toolItems.map((item, i) => (
              <Reveal key={item} delay={i * 0.04}>
                <Card className="flex flex-col items-center gap-3 py-6 text-center text-sm font-semibold">
                  <span
                    className="flex h-10 w-10 items-center justify-center rounded-lg text-white"
                    style={{
                      background:
                        "linear-gradient(135deg, var(--cta-gradient-from), var(--cta-gradient-to))",
                    }}
                  >
                    {TOOL_ICONS[item]}
                  </span>
                  {item}
                </Card>
              </Reveal>
            ))}
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
            <h2 className="mb-3 text-[clamp(1.75rem,3.5vw,2.5rem)] text-white">{t("cta.title")}</h2>
            <p className="mx-auto mb-7 max-w-[56ch] opacity-85">{t("cta.sub")}</p>
            <div className="flex flex-wrap items-center justify-center gap-x-6 gap-y-3">
              <ButtonLink href="/contact" style={{ background: "#fff", color: "var(--color-on-brand-light)" }}>
                {tc("ctaConfierProjet")}
              </ButtonLink>
              <a
                href="/cv-horlynat-mampassi-mbama.pdf"
                download
                className="text-sm font-semibold text-white opacity-90 hover:underline hover:opacity-100"
              >
                {tc("ctaTelechargerCv")} →
              </a>
            </div>
          </Reveal>
        </div>
      </section>
    </>
  );
}
