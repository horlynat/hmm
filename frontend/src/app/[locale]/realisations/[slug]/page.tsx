import { notFound } from "next/navigation";
import { getTranslations } from "next-intl/server";
import { Badge, ButtonLink } from "@/components/ui";
import { getProjectBySlug } from "@/lib/api/projects";

export default async function ProjectDetailPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  const [project, t, tc] = await Promise.all([
    getProjectBySlug(slug),
    getTranslations("projects"),
    getTranslations("common"),
  ]);

  if (!project) {
    notFound();
  }

  const tStatus = await getTranslations("projects.status");

  return (
    <section className="px-6 py-16">
      <div className="mx-auto max-w-[840px]">
        <Badge variant="outline" className="mb-4">
          {tStatus(project.status)}
        </Badge>
        <h1 className="mb-5 text-[clamp(2rem,3.8vw,2.9rem)] leading-[1.14]">
          {project.title}
        </h1>
        <p className="mb-8 text-[1.05rem] opacity-75">{project.description}</p>
        {project.link && (
          <a
            href={project.link}
            target="_blank"
            rel="noopener"
            className="btn-primary mb-8 inline-flex"
          >
            {tc("seeProject")} →
          </a>
        )}
        <div className="border-t border-[var(--border-softer)] pt-6">
          <ButtonLink href="/realisations" variant="secondary">
            {t("eyebrow")} ←
          </ButtonLink>
        </div>
      </div>
    </section>
  );
}
