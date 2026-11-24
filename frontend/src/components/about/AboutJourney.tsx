import { getTranslations } from "next-intl/server";
import { QualificationsList } from "@/components/sections/QualificationsList";
import { Timeline } from "@/components/sections/Timeline";
import { formatDateRange } from "@/lib/date";
import type { AboutContent, Course, CourseType, Experience } from "@/lib/types";

/**
 * « Parcours » (prototype de refonte, section #parcours) : repères chiffrés,
 * puis expériences et formations côte à côte, puis l'encart « Toujours en
 * formation ». Repères et encart viennent d'AboutContent ; expériences et
 * formations de leurs propres écrans d'administration.
 */
export async function AboutJourney({
  locale,
  content,
  experiences,
  courses,
}: {
  locale: string;
  content: AboutContent;
  experiences: Experience[];
  courses: Course[];
}) {
  const t = await getTranslations({ locale, namespace: "about" });
  const tc = await getTranslations({ locale, namespace: "common" });

  return (
    <section id="parcours" className="scroll-mt-28 border-y border-[var(--border-softer)] bg-bg-card px-6 py-16">
      <div className="mx-auto max-w-[1120px] [&_p]:text-left">
        <span className="badge mb-3.5">{t("journey.eyebrow")}</span>
        <h2 className="mb-2 text-[clamp(1.75rem,3.5vw,2.5rem)]">{t("journey.title")}</h2>
        <p className="mb-10 max-w-[60ch] text-[var(--color-muted)]">{t("journey.lede")}</p>

        {content.journeyStats.length > 0 && (
          <dl className="mb-12 grid grid-cols-1 gap-4 sm:grid-cols-3">
            {content.journeyStats.map((stat, i) => (
              <div key={`${i}-${stat.value}`} className="soft-card p-5">
                <dt className="sr-only">{stat.label}</dt>
                <dd>
                  <b className="block text-2xl font-extrabold text-brand-dark" style={{ fontFamily: "var(--font-heading)" }}>
                    {stat.value}
                  </b>
                  <span className="text-sm text-[var(--color-muted)]">{stat.label}</span>
                </dd>
              </div>
            ))}
          </dl>
        )}

        <div className="grid grid-cols-1 gap-12 md:grid-cols-2">
          <div>
            <h3 className="mb-6 text-xl" style={{ fontFamily: "var(--font-heading)" }}>
              {t("experience.eyebrow")}
            </h3>
            {experiences.length > 0 ? (
              <Timeline
                items={experiences.map((e) => ({
                  title: `${e.role} — ${e.company}`,
                  desc: e.description,
                  date: formatDateRange(e.startDate, e.endDate, tc("present")),
                }))}
              />
            ) : (
              <p className="text-sm opacity-60">{tc("aVenir")}</p>
            )}
          </div>
          <div>
            <h3 className="mb-6 text-xl" style={{ fontFamily: "var(--font-heading)" }}>
              {t("formation.eyebrow")}
            </h3>
            {courses.length > 0 ? (
              <QualificationsList
                courses={courses}
                labels={
                  {
                    diplome: t("formation.typeDiploma"),
                    certification: t("formation.typeCertification"),
                    formation: t("formation.typeTraining"),
                  } satisfies Record<CourseType, string>
                }
              />
            ) : (
              <p className="text-sm opacity-60">{tc("aVenir")}</p>
            )}
            {content.learningText.trim() !== "" && (
              <div className="mt-8 rounded-[var(--radius-md)] border border-brand-primary/30 bg-brand-primary/10 p-5">
                <b className="mb-1 block">{t("journey.learningTitle")}</b>
                <p className="text-sm text-[var(--color-muted)]">{content.learningText}</p>
              </div>
            )}
          </div>
        </div>
      </div>
    </section>
  );
}
