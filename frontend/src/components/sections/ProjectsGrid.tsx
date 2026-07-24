"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { Chip } from "@/components/ui";
import { ProjectCard } from "./ProjectCard";
import type { Project, ProjectStatus } from "@/lib/types";

const STATUSES: ProjectStatus[] = [
  "a_venir",
  "en_cours",
  "collaboration",
  "termine",
  "suspendu",
];

export function ProjectsGrid({ projects }: { projects: Project[] }) {
  const t = useTranslations("projects");
  const tStatus = useTranslations("projects.status");
  const [filter, setFilter] = useState<ProjectStatus | "all">("all");

  const activeStatuses = STATUSES.filter((status) =>
    projects.some((p) => p.status === status),
  );
  const visible =
    filter === "all" ? projects : projects.filter((p) => p.status === filter);

  return (
    <div>
      <div className="mb-8 flex flex-wrap gap-2.5">
        <Chip active={filter === "all"} onClick={() => setFilter("all")}>
          {t("list.filterAll")}
        </Chip>
        {activeStatuses.map((status) => (
          <Chip
            key={status}
            active={filter === status}
            onClick={() => setFilter(status)}
          >
            {tStatus(status)}
          </Chip>
        ))}
      </div>
      {visible.length > 0 ? (
        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {visible.map((project) => (
            <ProjectCard key={project.id} project={project} />
          ))}
        </div>
      ) : (
        <p className="text-sm opacity-60">{t("list.empty")}</p>
      )}
    </div>
  );
}
