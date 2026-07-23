import { getTranslations } from "next-intl/server";
import { Badge, ButtonLink, Card } from "@/components/ui";
import { ProjectsGrid } from "@/components/sections";
import { getProjects } from "@/lib/api/projects";

export default async function ProjectsPage() {
  const t = await getTranslations("projects");
  const tc = await getTranslations("common");
  const projects = await getProjects();

  return (
    <>
      <section className="px-6 pt-14 pb-8">
        <div className="mx-auto max-w-[1120px]">
          <Badge variant="accent" className="mb-4">
            {t("eyebrow")}
          </Badge>
          <h1 className="mb-5 text-[clamp(2rem,3.8vw,2.9rem)] leading-[1.14]">
            {t("title")} <span className="text-brand-primary">{t("titleAccent")}</span>
          </h1>
          <p className="max-w-[60ch] text-[1.05rem] opacity-75">{t("sub")}</p>
        </div>
      </section>

      <section className="px-6 py-10">
        <div className="mx-auto max-w-[1120px]">
          <Badge className="mb-3.5">{t("caseStudy.eyebrow")}</Badge>
          <h2 className="mb-2 text-[clamp(1.5rem,3vw,2rem)]">{t("caseStudy.title")}</h2>
          <p className="mb-8 max-w-[60ch] opacity-70">{t("caseStudy.lede")}</p>
          <Card variant="soft" className="grid overflow-hidden p-0 lg:grid-cols-2">
            <div
              className="flex flex-col gap-3 p-8 text-white"
              style={{
                background:
                  "linear-gradient(135deg, var(--color-brand-dark), var(--color-brand-primary) 80%)",
              }}
            >
              <Badge className="w-fit bg-white/20 text-white">{t("caseStudy.badge")}</Badge>
              <h3 className="text-xl font-semibold text-white" style={{ fontFamily: "var(--font-heading)" }}>
                {t("caseStudy.cardTitle")}
              </h3>
              <p className="text-sm opacity-85">{t("caseStudy.cardText")}</p>
              <div className="mt-2 flex gap-2 text-xs">
                {["Symfony", "API Platform", "Next.js", "Assistant IA"].map((n) => (
                  <div key={n} className="flex-1 rounded-lg border border-white/25 bg-white/10 py-2.5 text-center">
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
        </div>
      </section>

      <section className="px-6 py-10">
        <div className="mx-auto max-w-[1120px]">
          <Badge className="mb-3.5">{t("list.eyebrow")}</Badge>
          <h2 className="mb-2 text-[clamp(1.5rem,3vw,2rem)]">{t("list.title")}</h2>
          <p className="mb-8 max-w-[60ch] opacity-70">{t("list.lede")}</p>
          <ProjectsGrid projects={projects} />
        </div>
      </section>

      <section className="px-6 py-10">
        <div className="card mx-auto max-w-[1120px] border-dashed py-10 text-center">
          <Badge variant="accent" className="mb-2">
            {t("invite.badge")}
          </Badge>
          <p className="mx-auto max-w-[52ch] text-sm opacity-70">{t("invite.text")}</p>
        </div>
      </section>

      <section className="px-6 py-16">
        <div className="card mx-auto max-w-[1120px] py-12 text-center">
          <h2 className="mb-3 text-[clamp(1.5rem,3vw,2rem)]">{t("cta.title")}</h2>
          <p className="mx-auto mb-7 max-w-[56ch] opacity-70">{t("cta.sub")}</p>
          <ButtonLink href="/contact">{tc("ctaConfierProjet")}</ButtonLink>
        </div>
      </section>
    </>
  );
}
