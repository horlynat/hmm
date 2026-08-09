import { getTranslations } from "next-intl/server";
import { Badge, Card, HeroBackground, Reveal } from "@/components/ui";
import { Link } from "@/i18n/navigation";
import { helpCenterContent } from "@/content/help-center";

export const dynamic = "force-static";

export default async function HelpCenterPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: "helpCenter" });
  const content = helpCenterContent[locale === "en" ? "en" : "fr"];

  return (
    <>
      <section className="relative overflow-hidden px-6 pt-16 pb-12">
        <HeroBackground />
        <div className="relative mx-auto max-w-[760px] text-center">
          <Badge variant="accent" className="hero-in mb-4" style={{ animationDelay: "0s" }}>
            {t("eyebrow")}
          </Badge>
          <h1
            className="hero-in mb-5 text-[clamp(1.75rem,3vw,2.75rem)] leading-[1.25]"
            style={{ animationDelay: "0.08s" }}
          >
            {t("title")} <span className="text-brand-primary">{t("titleAccent")}</span>
          </h1>
          <p className="hero-in mx-auto max-w-[56ch] text-[1.05rem] opacity-75" style={{ animationDelay: "0.16s" }}>
            {t("sub")}
          </p>
        </div>
      </section>

      <section className="mx-auto max-w-[760px] px-6 pb-16">
        <Reveal as="div">
          <h2 className="mb-6 text-xl font-bold" style={{ fontFamily: "var(--font-heading)" }}>
            {t("docsHeading")}
          </h2>
        </Reveal>
        <div className="grid gap-4 sm:grid-cols-2">
          {content.docs.map((doc, index) => (
            <Reveal key={doc.title} as="div" delay={index * 0.05}>
              <Card variant="soft" className="h-full p-5">
                <h3 className="mb-2 font-semibold text-brand-primary">{doc.title}</h3>
                <p className="text-sm leading-relaxed opacity-75">{doc.body}</p>
              </Card>
            </Reveal>
          ))}
        </div>
      </section>

      <section className="mx-auto max-w-[760px] px-6 pb-16">
        <Reveal as="div">
          <h2 className="mb-6 text-xl font-bold" style={{ fontFamily: "var(--font-heading)" }}>
            {t("faqHeading")}
          </h2>
        </Reveal>
        <div className="space-y-3">
          {content.faq.map((entry, index) => (
            <Reveal key={entry.question} as="div" delay={index * 0.04}>
              <details className="group rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] px-5 py-4">
                <summary className="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold">
                  {entry.question}
                  <span className="shrink-0 text-brand-primary transition-transform group-open:rotate-45" aria-hidden="true">
                    +
                  </span>
                </summary>
                <p className="mt-3 text-sm leading-relaxed opacity-75">{entry.answer}</p>
              </details>
            </Reveal>
          ))}
        </div>
      </section>

      <section className="px-6 pb-20">
        <Reveal as="div">
          <Card variant="soft" className="mx-auto max-w-[760px] p-8 text-center">
            <h2 className="mb-2 text-lg font-bold" style={{ fontFamily: "var(--font-heading)" }}>
              {t("ctaTitle")}
            </h2>
            <p className="mb-5 text-sm opacity-75">{t("ctaText")}</p>
            <Link href="/support/nouveau" className="btn-primary">
              {t("ctaButton")}
            </Link>
          </Card>
        </Reveal>
      </section>
    </>
  );
}
