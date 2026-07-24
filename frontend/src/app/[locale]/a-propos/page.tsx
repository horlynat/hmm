import { getTranslations } from "next-intl/server";
import { Badge, ButtonLink, Card } from "@/components/ui";
import { SkillChip, Timeline } from "@/components/sections";
import { getExperiences } from "@/lib/api/experiences";
import { getCourses } from "@/lib/api/courses";
import { getSkills } from "@/lib/api/skills";

export default async function AboutPage() {
  const t = await getTranslations("about");
  const tc = await getTranslations("common");

  const [experiences, courses, skills] = await Promise.all([
    getExperiences(),
    getCourses(),
    getSkills(),
  ]);

  return (
    <>
      <section className="px-6 pt-14 pb-10">
        <div className="mx-auto grid max-w-[1120px] gap-12 lg:grid-cols-[1.1fr_0.9fr] lg:items-center">
          <div>
            <Badge variant="accent" className="mb-4">
              {t("eyebrow")}
            </Badge>
            <h1 className="mb-5 text-[clamp(2rem,3.8vw,2.9rem)] leading-[1.14]">
              {t("title")} <span className="text-brand-primary">{t("titleAccent")}</span>
            </h1>
            <p className="max-w-[50ch] text-[1.05rem] opacity-75">{t("sub")}</p>
          </div>
          <Card variant="soft" className="p-7 text-center">
            <div
              className="mx-auto mb-4 flex h-[88px] w-[88px] items-center justify-center rounded-full text-2xl font-extrabold text-white"
              style={{
                fontFamily: "var(--font-heading)",
                background:
                  "linear-gradient(135deg, var(--color-brand-dark), var(--color-brand-primary))",
              }}
            >
              HM
            </div>
            <div className="mb-1 text-lg font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
              {t("card.name")}
            </div>
            <div className="mb-5 text-sm opacity-65">{t("card.role")}</div>
            <ul className="list-none space-y-2 border-t border-[var(--border-softer)] p-0 pt-4 text-left">
              <li className="flex justify-between text-sm">
                <span className="opacity-60">{t("card.dispo")}</span>
                <span className="font-semibold text-success">{t("card.disponible")}</span>
              </li>
              <li className="flex justify-between text-sm">
                <span className="opacity-60">{t("card.aussi")}</span>
                <span className="font-semibold">{t("card.aussiValue")}</span>
              </li>
              <li className="flex justify-between text-sm">
                <span className="opacity-60">{t("card.localisation")}</span>
                <span className="font-semibold">{t("card.localisationValue")}</span>
              </li>
              <li className="flex justify-between text-sm">
                <span className="opacity-60">{t("card.mode")}</span>
                <span className="font-semibold">{t("card.modeValue")}</span>
              </li>
              <li className="flex justify-between text-sm">
                <span className="opacity-60">{t("card.langues")}</span>
                <span className="font-semibold">{t("card.languesValue")}</span>
              </li>
            </ul>
            <a href="/cv-horlynat-mampassi-mbama.pdf" download className="btn-secondary mt-5 w-full">
              {tc("ctaTelechargerCv")}
            </a>
          </Card>
        </div>
      </section>

      <section className="px-6 py-16">
        <div className="mx-auto max-w-[1120px]">
          <Badge className="mb-3.5">{t("bio.eyebrow")}</Badge>
          <h2 className="mb-6 text-[clamp(1.5rem,3vw,2rem)]">{t("bio.title")}</h2>
          <div className="max-w-[72ch] space-y-4 opacity-78">
            <p>{t("bio.p1")}</p>
            <p>{t("bio.p2")}</p>
            <p>{t("bio.p3")}</p>
          </div>
        </div>
      </section>

      <section className="px-6 py-16">
        <div className="mx-auto max-w-[1120px]">
          <Badge className="mb-3.5">{t("vision.eyebrow")}</Badge>
          <h2 className="mb-2 text-[clamp(1.5rem,3vw,2rem)]">{t("vision.title")}</h2>
          <p className="mb-10 max-w-[60ch] opacity-70">{t("vision.lede")}</p>
          <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
            <div className="card">
              <Badge variant="accent" className="mb-4">
                {t("vision.today")}
              </Badge>
              <p className="text-sm opacity-75">{t("vision.todayText")}</p>
            </div>
            <div className="card">
              <Badge variant="outline" className="mb-4">
                {t("vision.tomorrow")}
              </Badge>
              <p className="text-sm opacity-75">{t("vision.tomorrowText")}</p>
            </div>
          </div>
        </div>
      </section>

      <section className="px-6 py-16">
        <div className="mx-auto grid max-w-[1120px] gap-12 lg:grid-cols-2">
          <div>
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
          </div>
          <div>
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
          </div>
        </div>
      </section>

      <section className="px-6 py-16">
        <div className="mx-auto max-w-[1120px]">
          <Badge className="mb-3.5">{t("skillsDetail.eyebrow")}</Badge>
          <h2 className="mb-2 text-[clamp(1.5rem,3vw,2rem)]">{t("skillsDetail.title")}</h2>
          <p className="mb-10 max-w-[60ch] opacity-70">{t("skillsDetail.lede")}</p>
          {skills.length > 0 ? (
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {skills.map((skill) => (
                <SkillChip key={skill.id} skill={skill} />
              ))}
            </div>
          ) : (
            <p className="text-sm opacity-60">{tc("aVenir")}</p>
          )}
        </div>
      </section>

      <section className="px-6 py-16">
        <div className="mx-auto max-w-[1120px]">
          <Badge className="mb-3.5">{t("why.eyebrow")}</Badge>
          <h2 className="mb-10 text-[clamp(1.5rem,3vw,2rem)]">{t("why.title")}</h2>
          <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
            {[
              [t("why.card1Title"), t("why.card1Desc")],
              [t("why.card2Title"), t("why.card2Desc")],
              [t("why.card3Title"), t("why.card3Desc")],
              [t("why.card4Title"), t("why.card4Desc")],
            ].map(([title, desc], i) => (
              <div key={title} className="card flex gap-4">
                <div
                  className="w-8 shrink-0 text-2xl font-extrabold text-brand-accent"
                  style={{ fontFamily: "var(--font-heading)" }}
                >
                  {String(i + 1).padStart(2, "0")}
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

      <section className="px-6 py-16">
        <div className="mx-auto max-w-[1120px]">
          <Badge className="mb-3.5">{t("beyond.eyebrow")}</Badge>
          <h2 className="mb-6 text-xl" style={{ fontFamily: "var(--font-heading)" }}>
            {t("beyond.title")}
          </h2>
          <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
            <div className="card">
              <div className="mb-2.5 font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
                {t("beyond.langues")}
              </div>
              <div className="flex flex-wrap gap-1.5">
                <Badge variant="outline">{t("beyond.langue1")}</Badge>
                <Badge variant="outline">{t("beyond.langue2")}</Badge>
              </div>
            </div>
            <div className="card">
              <div className="mb-2.5 font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
                {t("beyond.interets")}
              </div>
              <div className="flex flex-wrap gap-1.5">
                <Badge variant="outline">{t("beyond.interet1")}</Badge>
                <Badge variant="outline">{t("beyond.interet2")}</Badge>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section
        className="px-6 py-16 text-center text-white"
        style={{
          background:
            "linear-gradient(135deg, var(--color-brand-dark), var(--color-brand-primary) 80%)",
        }}
      >
        <div className="mx-auto max-w-[1120px]">
          <h2 className="mb-3 text-[clamp(1.5rem,3vw,2rem)] text-white">{t("cta.title")}</h2>
          <p className="mx-auto mb-7 max-w-[56ch] opacity-85">{t("cta.sub")}</p>
          <ButtonLink href="/contact" style={{ background: "#fff", color: "var(--color-brand-dark)" }}>
            {tc("ctaConfierProjet")}
          </ButtonLink>
        </div>
      </section>
    </>
  );
}
