"use client";

import { useTranslations } from "next-intl";
import Image from "next/image";
import type { Project } from "@/lib/types";
import { Link } from "@/i18n/navigation";
import { Badge, Card } from "@/components/ui";
import { ProjectVisual } from "./ProjectVisual";
import { getMediaUrl } from "@/lib/media";
import { projectStatusVariant } from "@/lib/status";

/** Nombre de technologies affichées par carte — assez pour donner une idée de la
 * stack sans faire déborder une carte de largeur réduite (grille 2 colonnes). */
const MAX_TECH_BADGES = 3;

/** Longueur max de la description affichée sur la carte — harmonise la hauteur
 * des cartes entre elles plutôt que de laisser un texte long déborder. */
const MAX_DESCRIPTION_LENGTH = 300;

function truncateDescription(text: string, length = MAX_DESCRIPTION_LENGTH) {
  return text.length > length ? `${text.slice(0, length).trimEnd()}…` : text;
}

export function ProjectCard({
  project,
  className,
}: {
  project: Project;
  className?: string;
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
        <div className="relative h-[130px] w-full overflow-hidden bg-brand-light">
          {coverImage ? (
            <Image
              src={getMediaUrl(coverImage.filePath)}
              alt={coverImage.altText ?? project.title}
              fill
              sizes="(min-width: 1024px) 360px, (min-width: 640px) 50vw, 100vw"
              className="object-cover"
            />
          ) : (
            <ProjectVisual seed={project.id} />
          )}
          {/* Badge de statut en incrustation, coin supérieur droit — même
              position sur toutes les pages (grille et page projet) pour une
              lecture cohérente du statut d'un coup d'œil. */}
          <Badge variant={projectStatusVariant(project.status)} className="absolute top-3 right-3 shadow-sm">
            {t(project.status)}
          </Badge>
        </div>
        <div className="flex flex-1 flex-col p-5">
          <div
            className="mb-2 text-[1.02rem] font-semibold"
            style={{ fontFamily: "var(--font-heading)" }}
          >
            {project.title}
          </div>
          <p className="flex-1 text-base leading-relaxed opacity-70">
            {truncateDescription(project.description)}
          </p>
          <div className="mt-4 flex flex-wrap gap-1.5">
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
