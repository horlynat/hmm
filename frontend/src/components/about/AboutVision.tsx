import { getTranslations } from "next-intl/server";
import type { AboutContent } from "@/lib/types";

/**
 * « Ma vision » (prototype de refonte, section #vision) : citation, puis
 * cartes Vision / Mission / Ambition, puis ambitions dans le temps.
 *
 * Tout vient du back-office (AboutContent). Chaque bloc est facultatif : un
 * texte vide masque sa carte, et une rangée entièrement vide disparaît.
 */
export async function AboutVision({ locale, content }: { locale: string; content: AboutContent }) {
  const t = await getTranslations({ locale, namespace: "about" });

  const pillars = [
    { title: t("pillars.vision"), text: content.visionPillar },
    { title: t("pillars.mission"), text: content.missionPillar },
    { title: t("pillars.ambition"), text: content.ambitionPillar },
  ].filter((p) => p.text.trim() !== "");

  const horizons = [
    { label: t("horizons.today"), text: content.visionTodayText },
    { label: t("horizons.midTerm"), text: content.horizonMidText },
    { label: t("horizons.longTerm"), text: content.visionTomorrowText },
  ].filter((h) => h.text.trim() !== "");

  return (
    <section id="vision" className="scroll-mt-28 border-y border-[var(--border-softer)] bg-bg-card px-6 py-16">
      <div className="mx-auto max-w-[1120px] [&_p]:text-left">
        <span className="badge mb-3.5">{t("vision.eyebrow")}</span>
        <h2 className="mb-4 text-[clamp(1.75rem,3.5vw,2.5rem)]">{content.visionTitle}</h2>
        {content.visionLede.trim() !== "" && (
          <blockquote className="mb-12 max-w-[62ch] border-l-2 border-brand-primary pl-5 text-[clamp(1.1rem,2vw,1.35rem)] leading-relaxed text-brand-dark">
            <p>{content.visionLede}</p>
          </blockquote>
        )}

        {pillars.length > 0 && (
          <div className="mb-12 grid grid-cols-1 gap-5 md:grid-cols-3">
            {pillars.map((p, i) => (
              <div key={p.title} className="soft-card p-6">
                <span className="font-mono text-xs font-bold text-brand-primary">0{i + 1}</span>
                <h3 className="mt-2 mb-2 text-lg" style={{ fontFamily: "var(--font-heading)" }}>
                  {p.title}
                </h3>
                <p className="text-sm text-[var(--color-muted)]">{p.text}</p>
              </div>
            ))}
          </div>
        )}

        {horizons.length > 0 && (
          <div>
            <p className="mb-4 text-xs font-bold tracking-wide text-[var(--color-muted)] uppercase">{t("horizons.title")}</p>
            <ol className="grid list-none grid-cols-1 gap-4 p-0 md:grid-cols-3">
              {horizons.map((h) => (
                <li key={h.label} className="border-t-2 border-brand-primary/40 pt-4">
                  <span className="text-sm font-semibold text-brand-primary">{h.label}</span>
                  <p className="mt-1.5 text-sm text-[var(--color-muted)]">{h.text}</p>
                </li>
              ))}
            </ol>
          </div>
        )}
      </div>
    </section>
  );
}
