import { getTranslations } from "next-intl/server";
import { Badge, Reveal } from "@/components/ui";

interface Step {
  title: string;
  desc: string;
}

/** « Six étapes claires, sans surprise » (prototype de refonte 2026-11). */
export async function StepsSection({ locale }: { locale: string }) {
  const t = await getTranslations({ locale, namespace: "home.steps" });
  const steps = t.raw("items") as Step[];

  return (
    <section id="methode" className="px-6 py-20">
      <div className="mx-auto max-w-[1120px]">
        <Reveal className="mb-10 text-center">
          <Badge className="mb-3.5">{t("eyebrow")}</Badge>
          <h2 className="mb-2 text-[clamp(1.75rem,3.5vw,2.5rem)]">{t("title")}</h2>
          <p className="mx-auto max-w-[60ch] text-[var(--color-muted)]">{t("lede")}</p>
        </Reveal>
        <ol className="grid list-none grid-cols-1 gap-5 p-0 sm:grid-cols-2 lg:grid-cols-3">
          {steps.map((step, i) => (
            <Reveal as="li" key={step.title} delay={i * 0.05} className="soft-card p-6">
              <span
                aria-hidden="true"
                className="mb-3 flex h-9 w-9 items-center justify-center rounded-full bg-brand-primary/15 font-mono text-sm font-bold text-brand-primary"
              >
                {i + 1}
              </span>
              <b className="mb-1.5 block" style={{ fontFamily: "var(--font-heading)" }}>{step.title}</b>
              <p className="text-sm text-[var(--color-muted)]">{step.desc}</p>
            </Reveal>
          ))}
        </ol>
      </div>
    </section>
  );
}
