"use client";

import { useTranslations } from "next-intl";
import Image from "next/image";
import type { Project } from "@/lib/types";
import { Link } from "@/i18n/navigation";
import { Badge, Card } from "@/components/ui";
import { ProjectVisual } from "./ProjectVisual";
import { getMediaUrl } from "@/lib/media";

/** Nombre de technologies affichées par carte — assez pour donner une idée de la
 * stack sans faire déborder une carte de largeur réduite (grille 3 colonnes). */
const MAX_TECH_BADGES = 3;

export function ProjectCard({
  project,
  className,
  featured = false,
}: {
  project: Project;
  className?: string;
  featured?: boolean;
}) {
  const t = useTranslations("projects.status");
  const tc = useTranslations("common");
  const coverImage = project.info?.coverImage;
  const techStack = project.info?.techStack.slice(0, MAX_TECH_BADGES) ?? [];

  return (
    <Card className={`flex flex-col overflow-hidden p-0 ${className ?? ""}`}>
      <Link
        href={{ pathname: "/realisations/[slug]", params: { slug: project.slug } }}
        className="contents"
      >
        <div
          className={`relative w-full overflow-hidden bg-brand-light ${featured ? "h-[220px]" : "h-[130px]"}`}
        >
          {coverImage ? (
            <Image
              src={getMediaUrl(coverImage.filePath)}
              alt={coverImage.altText ?? project.title}
              fill
              sizes={featured ? "(min-width: 768px) 50vw, 100vw" : "(min-width: 1024px) 360px, (min-width: 640px) 50vw, 100vw"}
              className="object-cover"
            />
          ) : (
            <ProjectVisual seed={project.id} />
          )}
        </div>
        <div className="flex flex-1 flex-col p-5">
          <div
            className={`mb-2 font-semibold ${featured ? "text-xl" : "text-[1.02rem]"}`}
            style={{ fontFamily: "var(--font-heading)" }}
          >
            {project.title}
          </div>
          <p className="flex-1 text-sm opacity-70">{project.description}</p>
          <div className="mt-4 flex flex-wrap gap-1.5">
            <Badge variant="outline">{t(project.status)}</Badge>
            {techStack.map((tech) => (
              <Badge key={tech.name} variant="neutral">
                {tech.name}
              </Badge>
            ))}
          </div>
        </div>
      </Link>
      <div className="flex flex-wrap gap-x-4 gap-y-2 px-5 pb-5">
        <Link
          href={{ pathname: "/realisations/[slug]", params: { slug: project.slug } }}
          className="text-sm font-semibold text-brand-primary hover:underline"
        >
          {tc("seeDetails")} →
        </Link>
        {project.link && (
          <a
            href={project.link}
            target="_blank"
            rel="noopener"
            className="text-sm font-semibold text-brand-primary hover:underline"
          >
            {tc("seeProject")} →
          </a>
        )}
      </div>
    </Card>
  );
}
