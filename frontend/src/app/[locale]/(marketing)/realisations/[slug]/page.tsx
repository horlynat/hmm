import type { Metadata } from "next";
import Image from "next/image";
import { notFound } from "next/navigation";
import { getTranslations } from "next-intl/server";
import { AnimatedStatValue, Badge, Breadcrumb, ButtonLink, Card, HeroBackground, ReadingProgressBar, Reveal } from "@/components/ui";
import { ProjectVisual } from "@/components/sections/ProjectVisual";
import { NextUpCard } from "@/components/sections/NextUpCard";
import { ProjectDeviceFrame } from "@/components/sections/ProjectDeviceFrame";
import { AiPageInsight } from "@/components/ai-assistant/AiPageInsight";
import { getProjectBySlug, getProjects, getProjectSlugs } from "@/lib/api/projects";
import { getMediaUrl } from "@/lib/media";
import { jsonLdScript } from "@/lib/json-ld";
import { siteConfig } from "@/config/site";
import { projectStatusVariant } from "@/lib/status";
import { resolveOgLocale, SITE_URL } from "@/lib/metadata";
import { getArticleExcerpt, sanitizeArticleHtml } from "@/lib/sanitize";
import { projectImageTransitionName } from "@/lib/viewTransitionNames";

export const dynamic = "force-static";

// Pré-génère une page par projet et par locale au build (cf. generateStaticParams
// "top-down" de [locale]/layout.tsx qui fournit `locale` — même liste de projets
// quelle que soit la langue, `locale` n'a donc pas besoin d'être utilisé ici).
export async function generateStaticParams() {
  const slugs = await getProjectSlugs();
  return slugs.map((slug) => ({ slug }));
}

export async function generateMetadata({
  params,
}: {
  params: Promise<{ slug: string; locale: string }>;
}): Promise<Metadata> {
  const { slug, locale } = await params;
  const project = await getProjectBySlug(slug, locale);

  if (!project) return {};

  // Sans image de couverture, repli sur l'image générée par
  // `[locale]/opengraph-image.tsx` — référencée explicitement (Next.js ne la
  // rattache pas automatiquement quand la route définit son propre
  // `openGraph`, cf. commentaire dans lib/metadata.ts).
  const image = project.info?.coverImage
    ? getMediaUrl(project.info.coverImage.filePath)
    : `${SITE_URL}/${locale}/opengraph-image`;

  // `description` peut désormais contenir du HTML structuré (éditeur riche
  // admin, cf. rich_text_controller.js côté backend) — extrait un résumé
  // texte brut pour les meta description / Open Graph / Twitter, qui
  // n'acceptent pas de balisage.
  const excerpt = getArticleExcerpt(project.description);

  return {
    title: project.title,
    description: excerpt,
    openGraph: {
      title: project.title,
      description: excerpt,
      type: "article",
      siteName: siteConfig.name,
      locale: resolveOgLocale(locale),
      images: [image],
    },
    twitter: {
      card: "summary_large_image",
      title: project.title,
      description: excerpt,
      images: [image],
    },
  };
}

function CheckIcon() {
  return (
    <svg aria-hidden="true" viewBox="0 0 20 20" width="18" height="18" className="mt-0.5 shrink-0 text-success">
      <circle cx="10" cy="10" r="9" fill="currentColor" opacity="0.15" />
      <path
        d="M6 10.2l2.5 2.5L14 7.5"
        fill="none"
        stroke="currentColor"
        strokeWidth="1.8"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}

export default async function ProjectDetailPage({
  params,
}: {
  params: Promise<{ slug: string; locale: string }>;
}) {
  const { slug, locale } = await params;
  const [project, projects, t, td, tc] = await Promise.all([
    getProjectBySlug(slug, locale),
    getProjects(locale),
    getTranslations({ locale, namespace: "projects" }),
    getTranslations({ locale, namespace: "projects.detail" }),
    getTranslations({ locale, namespace: "common" }),
  ]);

  if (!project) {
    notFound();
  }

  const tStatus = await getTranslations({ locale, namespace: "projects.status" });
  const info = project.info;

  // Projet suivant dans la liste (bouclé : le dernier renvoie au premier),
  // affiché en fin de page plutôt qu'un simple lien retour — cf. NextUpCard.
  // Masqué s'il n'y a qu'un seul projet publié (n'aurait rien à proposer).
  const currentIndex = projects.findIndex((p) => p.slug === project.slug);
  const nextProject = projects.length > 1 ? projects[(currentIndex + 1) % projects.length] : null;
  const nextProjectImage = nextProject?.info?.coverImage
    ? getMediaUrl(nextProject.info.coverImage.filePath)
    : undefined;
  // `project.media ?? []` : robuste si l'API ne renvoie pas encore ce champ
  // (cache de fetch antérieur à son passage en `api_public`, backend plus
  // ancien, etc.) — le frontend et le backend sont déployés séparément.
  const galleryMedia = (project.media ?? []).filter(
    (m) => m.id !== info?.coverImage?.id && m.id !== info?.architectureDiagram?.id,
  );

  const projectImage = info?.coverImage ? getMediaUrl(info.coverImage.filePath) : undefined;
  const projectJsonLd = {
    "@context": "https://schema.org",
    "@type": "CreativeWork",
    name: project.title,
    description: getArticleExcerpt(project.description),
    image: projectImage ? [projectImage] : undefined,
    author: { "@type": "Person", name: siteConfig.name },
    keywords: info?.techStack.length ? info.techStack.map((tech) => tech.name).join(", ") : undefined,
    url: project.link || undefined,
  };

  return (
    <>
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: jsonLdScript(projectJsonLd) }}
      />
      <ReadingProgressBar />
      {/* Même habillage (fond dégradé + grille de points) que le hero de
          toutes les autres pages publiques, cf. PageHero — jusqu'ici absent
          des pages de détail, qui tranchaient à plat sur le reste du site
          (h1 notamment réduit à une taille de sous-titre). */}
      <section className="relative overflow-hidden px-6 pt-14 pb-8">
        <HeroBackground />
        <div className="relative mx-auto max-w-[1120px]">
          <div className="mb-6">
            <Breadcrumb items={[{ label: t("eyebrow"), href: "/realisations" }, { label: project.title }]} />
          </div>
          <Badge variant="accent" className="hero-in mb-4" style={{ animationDelay: "0s" }}>
            {t("eyebrow")}
          </Badge>
          {/* Volontairement pas de classe hero-in sur le h1 : candidat LCP le
              plus probable de la page, cf. commentaire dans PageHero.tsx.
              Taille alignée sur PageHero.tsx (clamp(1.75rem,3vw,2.75rem)) —
              ni la taille de sous-titre d'origine (clamp(1.25rem,2.5vw,1.75rem))
              ni la taille surdimensionnée choisie ensuite (calée sur l'ancien
              h1 de l'article, lui-même trop grand) ne correspondaient au
              standard déjà établi sur le reste du site. */}
          <h1 className="mb-5 text-[clamp(1.75rem,3vw,2.75rem)] leading-[1.25]">
            {project.title}
          </h1>
          {info?.role && (
            <p className="hero-in mb-8 text-sm font-semibold text-brand-primary" style={{ animationDelay: "0.16s" }}>
              {td("roleLabel")} — {info.role}
            </p>
          )}
          <div className="hero-in flex flex-wrap gap-3.5" style={{ animationDelay: "0.24s" }}>
            {project.link ? (
              <>
                <a href={project.link} target="_blank" rel="noopener" className="btn-primary">
                  {tc("seeProject")} →
                </a>
                <ButtonLink href="/contact" variant="secondary">
                  {tc("ctaConfierProjet")}
                </ButtonLink>
              </>
            ) : (
              <ButtonLink href="/contact">{tc("ctaConfierProjet")}</ButtonLink>
            )}
            {info?.repoUrl && (
              <a
                href={info.repoUrl}
                target="_blank"
                rel="noopener"
                className="text-sm font-semibold text-brand-primary hover:underline"
              >
                {td("repoLink")} →
              </a>
            )}
          </div>
          {/* Dans le hero, pas en fin de page : un visiteur qui ne lit pas
              jusqu'au bout ne verrait jamais l'offre de résumé sinon. */}
          <div className="hero-in mt-6" style={{ animationDelay: "0.32s" }}>
            <AiPageInsight slug={project.slug} contentType="project" />
          </div>
        </div>
      </section>

      <section className="px-6 py-6">
        <div className="mx-auto max-w-[1120px]">
          <ProjectDeviceFrame liveUrl={project.link || undefined} liveLabel={tc("seeProject")}>
            <div
              className={info?.coverImage ? "vt-target relative h-[260px] w-full bg-brand-light sm:h-[320px]" : "relative h-[260px] w-full bg-brand-light sm:h-[320px]"}
              style={info?.coverImage ? { viewTransitionName: projectImageTransitionName(project.id) } : undefined}
            >
              {info?.coverImage ? (
                <Image
                  src={getMediaUrl(info.coverImage.filePath)}
                  alt={info.coverImage.altText ?? project.title}
                  fill
                  sizes="1120px"
                  className="object-cover"
                  priority
                />
              ) : (
                <ProjectVisual seed={project.id} />
              )}
              {/* Même position que ProjectCard (coin supérieur droit) : lecture
                  cohérente du statut, qu'on regarde une grille ou une page projet. */}
              <Badge variant={projectStatusVariant(project.status)} className="absolute top-3 right-3 shadow-sm">
                {tStatus(project.status)}
              </Badge>
            </div>
          </ProjectDeviceFrame>
        </div>
      </section>

      {/* Description du projet — après l'image plutôt que dans le hero
          (qui se déformait avec un bloc de texte de longueur variable) et
          sur 100% de la largeur, pas de colonne de lecture réduite : même
          contenu HTML rédigé côté admin Symfony (ROLE_ADMIN), sanitisé côté
          serveur en défense en profondeur avant injection — même traitement
          que le corps d'article, cf. blog/[slug]/page.tsx. */}
      <section className="px-6 py-6">
        <div className="mx-auto max-w-[1120px]">
          <Reveal delay={0}>
            <div
              className="article-body text-[1.05rem] opacity-75"
              dangerouslySetInnerHTML={{ __html: sanitizeArticleHtml(project.description) }}
            />
          </Reveal>
        </div>
      </section>

      {/* La preuve d'impact vient tout de suite après la couverture, avant
          l'architecture/la stack/les défis : on mène par le résultat plutôt
          que de le faire attendre en bas de page derrière les détails
          techniques — c'est le contenu le plus "vendeur" de la page. */}
      {info && info.results.length > 0 && (
        <section className="border-y border-[var(--border-softer)] bg-bg-card px-6 py-14">
          <div className="mx-auto max-w-[1120px]">
            <Reveal delay={0}>
              <h2 className="mb-6 text-lg font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
                {td("resultsLabel")}
              </h2>
              <div className="grid gap-4 sm:grid-cols-2 md:grid-cols-3">
                {info.results.map((result) => (
                  <div key={result.label} className="soft-card p-5 text-center">
                    <div
                      className="mb-1 text-[clamp(1.5rem,3vw,2rem)] font-semibold text-brand-primary"
                      style={{ fontFamily: "var(--font-heading)" }}
                    >
                      <AnimatedStatValue value={result.value} />
                    </div>
                    <div className="text-sm opacity-70">{result.label}</div>
                  </div>
                ))}
              </div>
            </Reveal>
          </div>
        </section>
      )}

      {info?.architectureDiagram && (
        <section className="px-6 py-6">
          <div className="mx-auto max-w-[1120px]">
            <Reveal delay={0}>
              <h2 className="mb-3 text-lg font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
                {td("architectureLabel")}
              </h2>
              <Card variant="soft" className="overflow-hidden p-0">
                <div className="relative h-[320px] w-full bg-bg-card sm:h-[420px]">
                  <Image
                    src={getMediaUrl(info.architectureDiagram.filePath)}
                    alt={info.architectureDiagram.altText ?? td("architectureLabel")}
                    fill
                    sizes="1120px"
                    className="object-contain"
                  />
                </div>
              </Card>
            </Reveal>
          </div>
        </section>
      )}

      {galleryMedia.length > 0 && (
        <section className="px-6 py-6">
          <div className="mx-auto max-w-[1120px]">
            <Reveal delay={0}>
              <h2 className="mb-3 text-lg font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
                {td("galleryLabel")}
              </h2>
              <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                {galleryMedia.map((media) => (
                  <div key={media.id} className="relative aspect-square overflow-hidden rounded-[var(--radius-md)] bg-bg-card">
                    <Image
                      src={getMediaUrl(media.filePath)}
                      alt={media.altText ?? project.title}
                      fill
                      sizes="(min-width: 640px) 360px, 50vw"
                      className="object-cover"
                    />
                  </div>
                ))}
              </div>
            </Reveal>
          </div>
        </section>
      )}

      {info && (info.objectives.length > 0 || info.techStack.length > 0) && (
        <section className="border-y border-[var(--border-softer)] bg-bg-card px-6 py-14">
          <div className="mx-auto grid max-w-[1120px] gap-10 md:grid-cols-2">
            {info.objectives.length > 0 && (
              <Reveal delay={0}>
                <h2 className="mb-4 text-lg font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
                  {td("objectivesLabel")}
                </h2>
                <ul className="flex flex-col gap-2.5">
                  {info.objectives.map((objective) => (
                    <li key={objective} className="flex items-start gap-2.5 text-sm opacity-80">
                      <CheckIcon />
                      {objective}
                    </li>
                  ))}
                </ul>
              </Reveal>
            )}
            {info.techStack.length > 0 && (
              <Reveal delay={0.1}>
                <h2 className="mb-4 text-lg font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
                  {td("stackLabel")}
                </h2>
                <div className="flex flex-col gap-3">
                  {info.techStack.map((tech) => (
                    <div key={tech.name} className="soft-card p-3.5">
                      <Badge variant="accent" className="mb-1.5 w-fit">
                        {tech.name}
                      </Badge>
                      {tech.rationale && <p className="text-sm opacity-70">{tech.rationale}</p>}
                    </div>
                  ))}
                </div>
              </Reveal>
            )}
          </div>
        </section>
      )}

      {info && info.challenges.length > 0 && (
        <section className="px-6 py-14">
          <div className="mx-auto max-w-[1120px]">
            <Reveal delay={0}>
              <h2 className="mb-6 text-lg font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
                {td("challengesLabel")}
              </h2>
            </Reveal>
            <div className="flex flex-col gap-4">
              {info.challenges.map((challenge, i) => (
                <Reveal key={challenge.problem} delay={i * 0.08}>
                  <Card variant="soft" className="grid gap-4 p-6 md:grid-cols-2">
                    <div>
                      <Badge variant="outline" className="mb-2">
                        {td("challengeLabel")}
                      </Badge>
                      <p className="text-sm opacity-80">{challenge.problem}</p>
                    </div>
                    <div>
                      <Badge className="mb-2">{td("solutionLabel")}</Badge>
                      <p className="text-sm opacity-80">{challenge.solution}</p>
                    </div>
                  </Card>
                </Reveal>
              ))}
            </div>
          </div>
        </section>
      )}

      <section className="px-6 py-10">
        <div className="mx-auto max-w-[1120px] border-t border-[var(--border-softer)] pt-6">
          <ButtonLink href="/realisations" variant="secondary" className="mb-6">
            {t("eyebrow")} ←
          </ButtonLink>
          {nextProject && (
            <Reveal delay={0}>
              <NextUpCard
                eyebrow={td("nextProject")}
                title={nextProject.title}
                cta={tc("seeDetails")}
                href={{ pathname: "/realisations/[slug]", params: { slug: nextProject.slug } }}
                image={nextProjectImage}
                imageAlt={nextProject.info?.coverImage?.altText ?? nextProject.title}
                imageTransitionName={projectImageTransitionName(nextProject.id)}
              />
            </Reveal>
          )}
        </div>
      </section>
    </>
  );
}
