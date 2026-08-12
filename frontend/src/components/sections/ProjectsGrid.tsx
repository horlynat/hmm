"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { Chip } from "@/components/ui";
import { ProjectCard } from "./ProjectCard";
import { sortProjectsForDisplay } from "@/lib/projects/sortForDisplay";
import type { Project, ProjectStatus } from "@/lib/types";

const STATUSES: ProjectStatus[] = [
  "a_venir",
  "en_cours",
  "collaboration",
  "termine",
  "suspendu",
];

/** Projets par page — au-delà, la pagination prend le relais plutôt que de
 * tout charger d'un coup. */
const PAGE_SIZE = 9;

export function ProjectsGrid({ projects }: { projects: Project[] }) {
  const t = useTranslations("projects");
  const tStatus = useTranslations("projects.status");
  const [filter, setFilter] = useState<ProjectStatus | "all">("all");
  const [page, setPage] = useState(1);

  function selectFilter(next: ProjectStatus | "all") {
    setFilter(next);
    setPage(1);
  }

  const sorted = sortProjectsForDisplay(projects);
  const activeStatuses = STATUSES.filter((status) =>
    sorted.some((p) => p.status === status),
  );
  const visible =
    filter === "all" ? sorted : sorted.filter((p) => p.status === filter);

  const totalPages = Math.max(1, Math.ceil(visible.length / PAGE_SIZE));
  const currentPage = Math.min(page, totalPages);
  const paged = visible.slice((currentPage - 1) * PAGE_SIZE, currentPage * PAGE_SIZE);

  return (
    <div>
      <div className="mb-8 flex flex-wrap gap-2.5">
        <Chip active={filter === "all"} onClick={() => selectFilter("all")}>
          {t("list.filterAll")}
        </Chip>
        {activeStatuses.map((status) => (
          <Chip
            key={status}
            active={filter === status}
            onClick={() => selectFilter(status)}
          >
            {tStatus(status)}
          </Chip>
        ))}
      </div>
      {paged.length > 0 ? (
        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
          {paged.map((project) => (
            <ProjectCard key={project.id} project={project} />
          ))}
        </div>
      ) : (
        <p className="text-sm opacity-60">{t("list.empty")}</p>
      )}
      {totalPages > 1 && (
        <div className="mt-8 flex items-center justify-center gap-2">
          <Chip
            disabled={currentPage === 1}
            onClick={() => setPage(currentPage - 1)}
            className="disabled:cursor-not-allowed disabled:opacity-40"
          >
            ← {t("list.previous")}
          </Chip>
          <span className="px-2 font-mono text-xs text-[var(--color-muted)]">
            {t("list.pageIndicator", { page: currentPage, total: totalPages })}
          </span>
          <Chip
            disabled={currentPage === totalPages}
            onClick={() => setPage(currentPage + 1)}
            className="disabled:cursor-not-allowed disabled:opacity-40"
          >
            {t("list.next")} →
          </Chip>
        </div>
      )}
    </div>
  );
}
