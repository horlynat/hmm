"use client";

import { useTranslations } from "next-intl";
import type { Project } from "@/lib/types";
import { Link } from "@/i18n/navigation";
import { Badge, Card } from "@/components/ui";
import { ProjectVisual } from "./ProjectVisual";

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

  return (
    <Card className={`flex flex-col overflow-hidden p-0 ${className ?? ""}`}>
      <Link
        href={{ pathname: "/realisations/[slug]", params: { slug: project.slug } }}
        className="contents"
      >
        <div className={`w-full bg-brand-light ${featured ? "h-[220px]" : "h-[130px]"}`}>
          <ProjectVisual seed={project.id} />
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
