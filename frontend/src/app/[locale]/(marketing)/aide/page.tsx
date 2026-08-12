import { getTranslations } from "next-intl/server";
import { Card, Reveal } from "@/components/ui";
import { PageHero } from "@/components/sections/PageHero";
import { Link } from "@/i18n/navigation";
import { helpCenterContent } from "@/content/help-center";
import { SparkleChatIcon, BookIcon } from "@/components/sections/AboutIcons";

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
      <PageHero
        eyebrow={t("eyebrow")}
        title={t("title")}
        titleAccent={t("titleAccent")}
        subtitle={t("sub")}
        subtitleClassName="max-w-[56ch]"
        aside={
          <Card variant="soft" className="hero-in p-6" style={{ animationDelay: "0.16s" }}>
            {/* Aperçu condensé (icône + titre) des mêmes docs détaillés plus bas — pas de contenu inventé. */}
            <ul className="list-none space-y-4 p-0">
              {content.docs.slice(0, 4).map((doc, index) => {
                const Icon = index % 2 === 0 ? SparkleChatIcon : BookIcon;
                return (
                  <li key={doc.title} className="flex items-center gap-3">
                    <span
                      aria-hidden="true"
                      className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-primary/10 text-brand-primary"
                    >
                      <Icon />
                    </span>
                    <span className="text-sm font-medium">{doc.title}</span>
                  </li>
                );
              })}
            </ul>
          </Card>
        }
      />

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
