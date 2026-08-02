import { getTranslations } from "next-intl/server";
import { Badge, Card, HeroBackground, Reveal } from "@/components/ui";
import { FreelanceForm } from "@/components/sections/FreelanceForm";

export const dynamic = "force-static";

export default async function FreelancesPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: "freelances" });

  return (
    <>
      <section className="relative overflow-hidden px-6 pt-16 pb-20">
        <HeroBackground />
        <div className="relative mx-auto grid max-w-[1120px] gap-10 md:grid-cols-[1.05fr_0.95fr] md:items-center">
          <div>
            <Badge variant="accent" className="hero-in mb-4" style={{ animationDelay: "0s" }}>
              {t("eyebrow")}
            </Badge>
            <h1
              className="hero-in mb-5 text-[clamp(1.75rem,3vw,2.75rem)] leading-[1.25]"
              style={{ animationDelay: "0.08s" }}
            >
              {t("title")} <span className="text-brand-primary">{t("titleAccent")}</span>
            </h1>
            <p
              className="hero-in mb-7 max-w-[48ch] text-[1.05rem] opacity-75"
              style={{ animationDelay: "0.16s" }}
            >
              {t("sub")}
            </p>
            <div
              className="hero-in flex flex-wrap items-center gap-x-6 gap-y-3"
              style={{ animationDelay: "0.24s" }}
            >
              <a href="#signup" className="btn-primary">
                {t("ctaCreate")}
              </a>
              <a href="#how" className="text-sm font-semibold text-brand-primary hover:underline">
                {t("how.eyebrow")} →
              </a>
            </div>
          </div>
          <div className="hero-in flex flex-wrap gap-3" style={{ animationDelay: "0.32s" }}>
            <Card variant="soft" className="min-w-[100px] flex-1 py-5 text-center">
              <div
                className="text-xl font-extrabold text-brand-primary"
                style={{ fontFamily: "var(--font-heading)" }}
              >
                100%
              </div>
              <div className="mt-1 font-mono text-[0.65rem] uppercase opacity-60">
                {t("stats.qualified")}
              </div>
            </Card>
            <Card variant="soft" className="min-w-[100px] flex-1 py-5 text-center">
              <div
                className="text-xl font-extrabold text-brand-primary"
                style={{ fontFamily: "var(--font-heading)" }}
              >
                1
              </div>
              <div className="mt-1 font-mono text-[0.65rem] uppercase opacity-60">
                {t("stats.contact")}
              </div>
            </Card>
            <Card variant="soft" className="min-w-[100px] flex-1 py-5 text-center">
              <div
                className="text-xl font-extrabold text-brand-primary"
                style={{ fontFamily: "var(--font-heading)" }}
              >
                ∞
              </div>
              <div className="mt-1 font-mono text-[0.65rem] uppercase opacity-60">
                {t("stats.open")}
              </div>
            </Card>
          </div>
        </div>
      </section>

      <section id="how" className="px-6 py-14">
        <div className="mx-auto max-w-[1120px]">
          <Reveal delay={0}>
            <Badge className="mb-3.5">{t("how.eyebrow")}</Badge>
            <h2 className="mb-2 text-[clamp(1.75rem,3.5vw,2.5rem)]">{t("how.title")}</h2>
            <p className="mb-10 max-w-[60ch] opacity-70">{t("how.lede")}</p>
          </Reveal>
          <div className="grid grid-cols-1 gap-5 md:grid-cols-3">
            {[
              [t("how.step1Title"), t("how.step1Desc")],
              [t("how.step2Title"), t("how.step2Desc")],
              [t("how.step3Title"), t("how.step3Desc")],
            ].map(([title, desc], i) => (
              <div key={title} className="card">
                <div
                  className="mb-2 text-2xl font-extrabold text-[var(--color-accent-text)]"
                  style={{ fontFamily: "var(--font-heading)" }}
                >
                  {String(i + 1).padStart(2, "0")}
                </div>
                <div className="mb-1.5 text-base font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
                  {title}
                </div>
                <div className="text-sm opacity-70">{desc}</div>
              </div>
            ))}
          </div>
        </div>
      </section>

      <section className="border-y border-[var(--border-softer)] bg-bg-card px-6 py-14">
        <div className="mx-auto max-w-[1120px]">
          <Reveal delay={0}>
            <Badge className="mb-3.5">{t("benefits.eyebrow")}</Badge>
            <h2 className="mb-10 text-[clamp(1.75rem,3.5vw,2.5rem)]">{t("benefits.title")}</h2>
          </Reveal>
          <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
            {[
              [t("benefits.b1Title"), t("benefits.b1Desc")],
              [t("benefits.b2Title"), t("benefits.b2Desc")],
              [t("benefits.b3Title"), t("benefits.b3Desc")],
              [t("benefits.b4Title"), t("benefits.b4Desc")],
            ].map(([title, desc], i) => (
              <div key={title} className="card flex gap-4">
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-light font-bold text-[var(--color-on-brand-light)]">
                  {i + 1}
                </div>
                <div>
                  <div className="mb-1 text-base font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
                    {title}
                  </div>
                  <div className="text-sm opacity-70">{desc}</div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      <section id="signup" className="px-6 py-14">
        <div className="mx-auto grid max-w-[1120px] gap-12 md:grid-cols-[0.85fr_1.15fr]">
          <Reveal delay={0}>
            <Badge className="mb-3.5">{t("signup.eyebrow")}</Badge>
            <h2 className="mb-4 text-[clamp(1.75rem,3.5vw,2.5rem)]">{t("signup.title")}</h2>
            <p className="mb-6 opacity-70">{t("signup.lede")}</p>
            <div className="space-y-4">
              {[
                [t("signup.faq1Q"), t("signup.faq1A")],
                [t("signup.faq2Q"), t("signup.faq2A")],
                [t("signup.faq3Q"), t("signup.faq3A")],
              ].map(([q, a]) => (
                <div key={q} className="card">
                  <div className="mb-1.5 text-sm font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
                    {q}
                  </div>
                  <p className="text-sm opacity-70">{a}</p>
                </div>
              ))}
            </div>
          </Reveal>
          <Reveal delay={0.1}>
            <FreelanceForm />
          </Reveal>
        </div>
      </section>
    </>
  );
}
