import { getTranslations } from "next-intl/server";
import { Badge, ButtonLink, Card } from "@/components/ui";
import { ProjectCard, ArticleCard, TestimonialCard } from "@/components/sections";
import { getProjects } from "@/lib/api/projects";
import { getArticles } from "@/lib/api/articles";
import { getTestimonials } from "@/lib/api/testimonials";

function StatCard({ num, label }: { num: string; label: string }) {
  return (
    <div className="card py-6 text-center">
      <div
        className="text-2xl font-extrabold text-brand-primary"
        style={{ fontFamily: "var(--font-heading)" }}
      >
        {num}
      </div>
      <div className="mt-1.5 font-mono text-xs uppercase tracking-wide opacity-60">
        {label}
      </div>
    </div>
  );
}

function ArchitectureDiagram({ caption }: { caption: string }) {
  return (
    <div>
      <svg viewBox="0 0 420 300" width="100%" role="img" aria-label={caption}>
        <rect x="30" y="30" width="120" height="60" rx="10" fill="var(--color-bg-default)" stroke="var(--border-soft)" strokeWidth="1.5" />
        <text x="90" y="56" textAnchor="middle" fontSize="12" fontWeight="700" fill="var(--color-brand-dark)">Symfony</text>
        <text x="90" y="74" textAnchor="middle" fontSize="9" fill="var(--color-brand-primary)">Backend admin</text>

        <rect x="270" y="30" width="120" height="60" rx="10" fill="var(--color-bg-default)" stroke="var(--border-soft)" strokeWidth="1.5" />
        <text x="330" y="56" textAnchor="middle" fontSize="12" fontWeight="700" fill="var(--color-brand-dark)">Next.js</text>
        <text x="330" y="74" textAnchor="middle" fontSize="9" fill="var(--color-brand-primary)">Frontend public</text>

        <rect x="150" y="120" width="120" height="60" rx="10" fill="var(--color-bg-default)" stroke="var(--color-brand-accent)" strokeWidth="2" />
        <text x="210" y="146" textAnchor="middle" fontSize="12" fontWeight="700" fill="var(--color-brand-dark)">API Platform</text>
        <text x="210" y="164" textAnchor="middle" fontSize="9" fill="var(--color-brand-primary)">Couche API</text>

        <rect x="150" y="210" width="120" height="60" rx="10" fill="var(--color-bg-default)" stroke="var(--border-soft)" strokeWidth="1.5" />
        <text x="210" y="236" textAnchor="middle" fontSize="12" fontWeight="700" fill="var(--color-brand-dark)">Assistant IA</text>
        <text x="210" y="254" textAnchor="middle" fontSize="9" fill="var(--color-brand-primary)">Profil · Qualification</text>

        <path d="M90 90 C90 130, 150 130, 150 150" fill="none" stroke="var(--border-soft)" strokeWidth="1.4" />
        <path d="M330 90 C330 130, 270 130, 270 150" fill="none" stroke="var(--border-soft)" strokeWidth="1.4" />
        <path d="M210 180 L210 210" fill="none" stroke="var(--border-soft)" strokeWidth="1.4" />
      </svg>
      <p className="mt-3 text-center font-mono text-xs opacity-55">{caption}</p>
    </div>
  );
}

export default async function HomePage() {
  const t = await getTranslations("home");
  const tc = await getTranslations("common");

  const [projects, articles, testimonials] = await Promise.all([
    getProjects(),
    getArticles(),
    getTestimonials(),
  ]);

  return (
    <>
      <section className="px-6 pt-16">
        <div className="mx-auto grid max-w-[1120px] gap-12 lg:grid-cols-[1.05fr_0.95fr] lg:items-center">
          <div>
            <Badge variant="accent" className="mb-4">
              {t("eyebrow")}
            </Badge>
            <h1 className="mb-5 text-[clamp(2.1rem,4vw,3.1rem)] leading-[1.12]">
              {t("title")}{" "}
              <span className="text-brand-primary">{t("titleAccent")}</span>
            </h1>
            <p className="mb-7 max-w-[48ch] text-[1.05rem] opacity-75">{t("sub")}</p>
            <div className="mb-7 flex flex-wrap gap-3.5">
              <ButtonLink href="/contact">{tc("ctaConfierProjet")}</ButtonLink>
              <a href="#freelance" className="btn-secondary">
                {t("ctaFreelance")}
              </a>
            </div>
            <div className="flex flex-wrap gap-2.5">
              <Badge variant="accent">Symfony</Badge>
              <Badge variant="accent">API Platform</Badge>
              <Badge variant="accent">Next.js</Badge>
              <Badge variant="outline">Assistant IA</Badge>
              <Badge variant="outline">Cybersécurité</Badge>
            </div>
          </div>
          <Card variant="soft" className="p-6">
            <ArchitectureDiagram caption={t("diagramCaption")} />
          </Card>
        </div>
      </section>

      <section className="border-y border-[var(--border-softer)] px-6 py-10">
        <div className="mx-auto grid max-w-[1120px] grid-cols-1 gap-6 sm:grid-cols-3">
          <StatCard num="3" label={t("stats.layers")} />
          <StatCard num="100%" label={t("stats.apiFirst")} />
          <StatCard num={t("stats.open")} label={t("stats.freelance")} />
        </div>
      </section>

      <section className="px-6 py-20">
        <div className="mx-auto grid max-w-[1120px] gap-12 lg:grid-cols-2">
          <div>
            <Badge className="mb-3.5">{t("about.eyebrow")}</Badge>
            <h2 className="mb-4 text-[clamp(1.5rem,3vw,2rem)]">{t("about.title")}</h2>
          </div>
          <div>
            <p className="mb-4 opacity-75">{t("about.p1")}</p>
            <p className="opacity-75">{t("about.p2")}</p>
          </div>
        </div>
      </section>

      <section className="px-6 py-20">
        <div className="mx-auto max-w-[1120px]">
          <Badge className="mb-3.5">{t("projects.eyebrow")}</Badge>
          <h2 className="mb-2 text-[clamp(1.5rem,3vw,2rem)]">{t("projects.title")}</h2>
          <p className="mb-10 max-w-[60ch] opacity-70">{t("projects.lede")}</p>
          {projects.length > 0 ? (
            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
              {projects.slice(0, 3).map((project) => (
                <ProjectCard key={project.id} project={project} />
              ))}
            </div>
          ) : (
            <p className="text-sm opacity-60">{t("projects.empty")}</p>
          )}
        </div>
      </section>

      <section className="px-6 py-20">
        <div className="mx-auto max-w-[1120px]">
          <Badge className="mb-3.5">{t("testimonials.eyebrow")}</Badge>
          <h2 className="mb-10 text-[clamp(1.5rem,3vw,2rem)]">{t("testimonials.title")}</h2>
          {testimonials.length > 0 ? (
            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
              {testimonials.slice(0, 3).map((testimonial) => (
                <TestimonialCard key={testimonial.id} testimonial={testimonial} />
              ))}
            </div>
          ) : (
            <p className="text-sm opacity-60">{t("testimonials.empty")}</p>
          )}
        </div>
      </section>

      <section className="px-6 py-20">
        <div className="mx-auto max-w-[1120px]">
          <Badge className="mb-3.5">{t("blog.eyebrow")}</Badge>
          <h2 className="mb-2 text-[clamp(1.5rem,3vw,2rem)]">{t("blog.title")}</h2>
          <p className="mb-10 max-w-[60ch] opacity-70">{t("blog.lede")}</p>
          {articles.length > 0 ? (
            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
              {articles.slice(0, 3).map((article) => (
                <ArticleCard key={article.id} article={article} />
              ))}
            </div>
          ) : (
            <p className="text-sm opacity-60">{t("blog.empty")}</p>
          )}
        </div>
      </section>

      <section
        id="freelance"
        className="px-6 py-20 text-white"
        style={{
          background:
            "linear-gradient(135deg, var(--color-brand-dark), var(--color-brand-primary) 80%)",
        }}
      >
        <div className="mx-auto grid max-w-[1120px] gap-12 lg:grid-cols-[1.1fr_0.9fr] lg:items-center">
          <div>
            <Badge className="mb-3.5 bg-white/15 text-white">{t("freelance.eyebrow")}</Badge>
            <h2 className="mb-3 text-[clamp(1.5rem,3vw,2rem)] text-white">
              {t("freelance.title")}
            </h2>
            <p className="mb-6 max-w-[60ch] opacity-85">{t("freelance.lede")}</p>
            <ul className="list-none space-y-3 p-0 text-sm">
              <li>✓ {t("freelance.point1")}</li>
              <li>✓ {t("freelance.point2")}</li>
              <li>✓ {t("freelance.point3")}</li>
            </ul>
          </div>
          <Card className="p-7">
            <Badge variant="accent" className="mb-3">
              {tc("ctaConfierProjet")}
            </Badge>
            <h3 className="mb-2 text-xl font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
              {t("freelance.cardTitle")}
            </h3>
            <p className="mb-5 text-sm opacity-70">{t("freelance.cardDesc")}</p>
            <ButtonLink href="/freelances" className="w-full">
              {t("freelance.cardCta")}
            </ButtonLink>
          </Card>
        </div>
      </section>

      <section className="px-6 py-20">
        <div className="card mx-auto max-w-[1120px] py-12 text-center">
          <h2 className="mb-3 text-[clamp(1.5rem,3vw,2rem)]">{t("contactCta.title")}</h2>
          <p className="mx-auto mb-7 max-w-[56ch] opacity-70">{t("contactCta.sub")}</p>
          <ButtonLink href="/contact">{tc("ctaConfierProjet")}</ButtonLink>
        </div>
      </section>
    </>
  );
}
