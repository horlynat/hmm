import { getTranslations } from "next-intl/server";
import { Activity, CheckCircle2, FolderKanban } from "lucide-react";
import { Link } from "@/i18n/navigation";
import { Badge, ButtonLink, Card, Reveal, StatCard } from "@/components/ui";
import { PageHero } from "@/components/sections/PageHero";
import { ProjectsGrid } from "@/components/sections/ProjectsGrid";
import { getProjects } from "@/lib/api/projects";

export const dynamic = "force-static";

export default async function ProjectsPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: "projects" });
  const tc = await getTranslations({ locale, namespace: "common" });
  const projects = await getProjects(locale);
  // Comptages dérivés de la liste déjà chargée (pas de nouvel appel API), pour
  // l'illustration du hero — mêmes statuts que le filtre de la section liste.
  const activeCount = projects.filter((p) => p.status === "en_cours").length;
  const completedCount = projects.filter((p) => p.status === "termine").length;

  return (
    <>
      <PageHero
        eyebrow={t("eyebrow")}
        title={t("title")}
        titleAccent={t("titleAccent")}
        subtitle={t("sub")}
        subtitleClassName="max-w-[60ch]"
        aside={
          <div className="hero-in grid gap-3" style={{ animationDelay: "0.16s" }}>
            <StatCard icon={FolderKanban} label={t("stats.statTotal")} value={projects.length} />
            <StatCard icon={Activity} label={t("stats.statActive")} value={activeCount} tone="success" />
            <StatCard icon={CheckCircle2} label={t("stats.statCompleted")} value={completedCount} />
          </div>
        }
      >
        <div
          className="hero-in mb-7 flex flex-wrap items-center gap-x-6 gap-y-3"
          style={{ animationDelay: "0.24s" }}
        >
          <ButtonLink href="/contact">{tc("ctaConfierProjet")}</ButtonLink>
          <a
            href="#case-study"
            className="text-sm font-semibold text-brand-primary hover:underline"
          >
            {t("caseStudy.eyebrow")} →
          </a>
        </div>
        <div className="hero-in flex flex-wrap gap-2.5" style={{ animationDelay: "0.32s" }}>
          <Badge variant="accent">Symfony</Badge>
          <Badge variant="accent">API Platform</Badge>
          <Badge variant="accent">Next.js</Badge>
          <Badge variant="outline">Assistant IA</Badge>
          <Badge variant="outline">Cybersécurité</Badge>
        </div>
      </PageHero>

      <section id="case-study" className="px-6 py-16">
        <div className="mx-auto max-w-[1120px]">
          <Reveal delay={0}>
            <Badge className="mb-3.5">{t("caseStudy.eyebrow")}</Badge>
            <h2 className="mb-2 text-[clamp(1.75rem,3.5vw,2.5rem)]">{t("caseStudy.title")}</h2>
            <p className="mb-8 max-w-[60ch] opacity-70">{t("caseStudy.lede")}</p>
          </Reveal>
          <Reveal delay={0.1}>
            <Card variant="soft" className="grid overflow-hidden p-0 md:grid-cols-2">
              <div
                className="flex flex-col justify-center gap-3 p-8 text-white"
                style={{
                  background:
                    "linear-gradient(135deg, var(--cta-gradient-from), var(--cta-gradient-to) 80%)",
                }}
              >
                <Badge className="w-fit bg-white/20 text-white">{t("caseStudy.badge")}</Badge>
                <h3
                  className="text-xl font-semibold text-white"
                  style={{ fontFamily: "var(--font-heading)" }}
                >
                  {t("caseStudy.cardTitle")}
                </h3>
                <p className="text-sm opacity-85">{t("caseStudy.cardText")}</p>
                <div className="mt-2 flex flex-wrap gap-2 text-xs">
                  {["Symfony", "API Platform", "Next.js", "Assistant IA"].map((n) => (
                    <div
                      key={n}
                      className="flex-1 rounded-lg border border-white/25 bg-white/10 py-2.5 text-center"
                    >
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
                {/* Pas de deuxième liste de badges tech ici : les 4 "couches"
                    du panneau de gauche couvrent déjà l'essentiel de la stack —
                    une seconde liste quasi identique juste en dessous n'ajoutait
                    que "Tailwind CSS v4" pour beaucoup de répétition visuelle. */}
                <p className="mb-5 text-sm opacity-75">{t("caseStudy.bodyText2")}</p>
                <ButtonLink href="/a-propos" variant="secondary">
                  {t("caseStudy.linkText")}
                </ButtonLink>
              </div>
            </Card>
          </Reveal>
        </div>
      </section>

      <section className="border-y border-[var(--border-softer)] bg-bg-card px-6 py-16">
        <div className="mx-auto max-w-[1120px]">
          <Reveal delay={0}>
            <Badge className="mb-3.5">{t("list.eyebrow")}</Badge>
            <h2 className="mb-2 text-[clamp(1.75rem,3.5vw,2.5rem)]">{t("list.title")}</h2>
            <p className="mb-8 max-w-[60ch] opacity-70">{t("list.lede")}</p>
          </Reveal>
          <ProjectsGrid projects={projects} />
        </div>
      </section>

      {/* Un seul bloc de clôture (pas deux quasi identiques dos-à-dos comme
          avant) : le lien "Proposer mon projet" — pour les clients passés
          dont le projet n'est pas encore listé ci-dessus — rejoint ici les
          autres liens plutôt que d'occuper toute une section dédiée. Il pointe
          vers le mode "Confier un projet" du Contact (?mode=projet), pas le
          mode par défaut ("Demander un devis") : le générique /contact
          renvoyait vers le mauvais panneau. */}
      <section
        className="px-6 py-16 text-center text-white"
        style={{
          background:
            "linear-gradient(135deg, var(--cta-gradient-from), var(--cta-gradient-to) 80%)",
        }}
      >
        <div className="mx-auto max-w-[1120px]">
          <Reveal delay={0}>
            <h2 className="mb-3 text-[clamp(1.75rem,3.5vw,2.5rem)] text-white">{t("cta.title")}</h2>
            <p className="mx-auto mb-7 max-w-[56ch] opacity-85">{t("cta.sub")}</p>
            <div className="flex flex-wrap items-center justify-center gap-x-6 gap-y-3">
              <ButtonLink href="/contact" style={{ background: "#fff", color: "var(--color-on-brand-light)" }}>
                {tc("ctaConfierProjet")}
              </ButtonLink>
              <Link
                href="/competences"
                className="text-sm font-semibold text-white opacity-90 hover:underline hover:opacity-100"
              >
                {t("cta.linkText")} →
              </Link>
              <Link
                href={{ pathname: "/contact", query: { mode: "projet" } }}
                className="text-sm font-semibold text-white opacity-90 hover:underline hover:opacity-100"
              >
                {t("cta.proposeLinkText")} →
              </Link>
            </div>
          </Reveal>
        </div>
      </section>
    </>
  );
}
