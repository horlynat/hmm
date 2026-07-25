"use client";

import { useTranslations } from "next-intl";
import type { Project } from "@/lib/types";
import { Badge, Card } from "@/components/ui";

export function ProjectCard({ project }: { project: Project }) {
  const t = useTranslations("projects.status");
  const tc = useTranslations("common");

  return (
    <Card className="flex flex-col overflow-hidden p-0">
      <div className="flex h-[130px] items-center justify-center bg-brand-light px-4 text-center">
        <span className="font-mono text-xs text-brand-dark/60">
          {project.title}
        </span>
      </div>
      <div className="flex flex-1 flex-col p-5">
        <div
          className="mb-2 text-[1.02rem] font-semibold"
          style={{ fontFamily: "var(--font-heading)" }}
        >
          {project.title}
        </div>
        <p className="flex-1 text-sm opacity-70">{project.description}</p>
        <div className="mt-4 flex flex-wrap gap-1.5">
          <Badge variant="outline">{t(project.status)}</Badge>
        </div>
        {project.link && (
          <a
            href={project.link}
            target="_blank"
            rel="noopener"
            className="mt-3 text-sm font-semibold text-brand-primary hover:underline"
          >
            {tc("seeProject")} →
          </a>
        )}
      </div>
    </Card>
  );
}
