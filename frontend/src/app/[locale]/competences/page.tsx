import { getTranslations } from "next-intl/server";
import { Badge, ButtonLink, Card } from "@/components/ui";
import { SkillChip } from "@/components/sections";
import { getSkills } from "@/lib/api/skills";

export default async function SkillsPage() {
  const t = await getTranslations("skills");
  const tc = await getTranslations("common");
  const skills = await getSkills();
  const softItems = t.raw("soft.items") as string[];
  const toolItems = t.raw("tools.items") as string[];

  return (
    <>
      <section className="px-6 pt-14 pb-10">
        <div className="mx-auto max-w-[1120px]">
          <Badge variant="accent" className="mb-4">
            {t("eyebrow")}
          </Badge>
          <h1 className="mb-5 max-w-[22ch] text-[clamp(2rem,3.8vw,2.9rem)] leading-[1.14]">
            {t("title")} <span className="text-brand-primary">{t("titleAccent")}</span>
          </h1>
          <p className="mb-5 max-w-[60ch] text-[1.05rem] opacity-75">{t("sub")}</p>
          <a href="/cv-horlynat-mampassi-mbama.pdf" download className="btn-secondary">
            {tc("ctaTelechargerCv")}
          </a>
        </div>
      </section>

      <section className="px-6 py-14">
        <div className="mx-auto max-w-[1120px]">
          <Badge className="mb-3.5">{t("list.eyebrow")}</Badge>
          <h2 className="mb-2 text-[clamp(1.5rem,3vw,2rem)]">{t("list.title")}</h2>
          <p className="mb-10 max-w-[60ch] opacity-70">{t("list.lede")}</p>
          {skills.length > 0 ? (
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
              {skills.map((skill) => (
                <SkillChip key={skill.id} skill={skill} />
              ))}
            </div>
          ) : (
            <p className="text-sm opacity-60">{t("list.empty")}</p>
          )}
        </div>
      </section>

      <section
        className="px-6 py-16 text-white"
        style={{
          background:
            "linear-gradient(135deg, var(--color-brand-dark), var(--color-brand-primary) 80%)",
        }}
      >
        <div className="mx-auto max-w-[1120px]">
          <Badge className="mb-3.5 bg-white/15 text-white">{t("domain.eyebrow")}</Badge>
          <h2 className="mb-2 text-[clamp(1.5rem,3vw,2rem)] text-white">{t("domain.title")}</h2>
          <p className="mb-10 max-w-[60ch] opacity-85">{t("domain.lede")}</p>
          <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
            <div className="rounded-[var(--radius-md)] border border-white/20 bg-white/8 p-6">
              <Badge className="mb-3 bg-white/20 text-white">{t("domain.cyberTitle")}</Badge>
              <p className="text-sm opacity-90">{t("domain.cyberText")}</p>
            </div>
            <div className="rounded-[var(--radius-md)] border border-white/20 bg-white/8 p-6">
              <Badge className="mb-3 bg-white/20 text-white">{t("domain.assuranceTitle")}</Badge>
              <p className="text-sm opacity-90">{t("domain.assuranceText")}</p>
            </div>
          </div>
        </div>
      </section>

      <section className="px-6 py-16">
        <div className="mx-auto max-w-[1120px]">
          <Badge className="mb-3.5">{t("soft.eyebrow")}</Badge>
          <h2 className="mb-2 text-[clamp(1.5rem,3vw,2rem)]">{t("soft.title")}</h2>
          <p className="mb-8 max-w-[60ch] opacity-70">{t("soft.lede")}</p>
          <div className="flex flex-wrap gap-2.5">
            {softItems.map((item) => (
              <Badge key={item} variant="outline">
                {item}
              </Badge>
            ))}
          </div>
        </div>
      </section>

      <section className="px-6 py-16">
        <div className="mx-auto max-w-[1120px]">
          <Badge className="mb-3.5">{t("tools.eyebrow")}</Badge>
          <h2 className="mb-8 text-[clamp(1.5rem,3vw,2rem)]">{t("tools.title")}</h2>
          <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
            {toolItems.map((item) => (
              <div key={item} className="card py-6 text-center text-sm font-semibold">
                {item}
              </div>
            ))}
          </div>
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
