import type { ReactNode } from "react";
import clsx from "clsx";

export type LegalSection = { title: string; body: ReactNode };

/** Mise en page partagée par les pages légales (mentions, confidentialité, cookies, CGU). */
export function LegalPageLayout({
  title,
  lastUpdate,
  intro,
  sections,
}: {
  title: string;
  lastUpdate: string;
  intro?: ReactNode;
  sections: LegalSection[];
}) {
  return (
    <section className="px-6 py-16">
      <div className="mx-auto max-w-[840px]">
        <h1 className="mb-2 text-[clamp(1.85rem,3.8vw,2.6rem)]">{title}</h1>
        <p className="mb-10 font-mono text-sm opacity-65">{lastUpdate}</p>

        {intro && <p className="mb-9 text-sm opacity-78">{intro}</p>}

        {sections.map(({ title: sectionTitle, body }, index) => (
          <div
            key={sectionTitle}
            className={clsx(
              "mb-9",
              index < sections.length - 1 && "border-b border-[var(--border-softer)] pb-8",
            )}
          >
            <h2
              className="mb-3 text-xl font-semibold"
              style={{ fontFamily: "var(--font-heading)" }}
            >
              {sectionTitle}
            </h2>
            <div className="space-y-3 text-sm opacity-78">{body}</div>
          </div>
        ))}
      </div>
    </section>
  );
}
