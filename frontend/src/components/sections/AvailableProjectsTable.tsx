"use client";

import { useMemo, useState } from "react";
import { useTranslations } from "next-intl";
import { Clock, Loader2, Rocket, Search } from "lucide-react";
import { Badge, Card, EmptyState } from "@/components/ui";
import { joinProject } from "@/lib/auth/actions";
import type { AvailableProject } from "@/lib/types";

/**
 * "Projets disponibles" du hub /compte/gestion-projet — barre de recherche +
 * table dense avec bouton "Postuler" par ligne (mockup validé), au lieu
 * de l'ancienne grille de cartes. Ni client ni budget en colonne : ces champs ne sont pas
 * exposés par GET /api/me/projects/available (cf. MeController::serializeProjectDetail —
 * volontairement réservés à l'admin), remplacés par Priorité, déjà exposée.
 */
export function AvailableProjectsTable({ projects: initialProjects }: { projects: AvailableProject[] }) {
  const t = useTranslations("auth.availableProjects");
  const tp = useTranslations("auth.projectManagement");
  const [projects, setProjects] = useState(initialProjects);
  const [query, setQuery] = useState("");
  const [joiningId, setJoiningId] = useState<number | null>(null);
  const [errorId, setErrorId] = useState<number | null>(null);

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return projects;
    return projects.filter(
      (p) =>
        p.title.toLowerCase().includes(q) ||
        p.skills.some((s) => s.name.toLowerCase().includes(q)) ||
        p.tags.some((tag) => tag.name.toLowerCase().includes(q)),
    );
  }, [projects, query]);

  async function handleJoin(id: number) {
    setJoiningId(id);
    setErrorId(null);
    const result = await joinProject(id);
    setJoiningId(null);
    if (result.ok) {
      setProjects((prev) => prev.map((p) => (p.id === id ? { ...p, joinPending: true } : p)));
    } else {
      setErrorId(id);
    }
  }

  if (projects.length === 0) {
    return <EmptyState icon={Rocket} message={t("empty")} />;
  }

  return (
    <div>
      <div className="mb-3 flex items-center gap-2 rounded-full border border-(--border-neutral) bg-bg-card px-3.5 py-2">
        <Search size={14} className="shrink-0 text-(--color-muted)" aria-hidden="true" />
        <input
          type="search"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder={tp("searchPlaceholder")}
          className="w-full min-w-0 border-0 bg-transparent text-sm outline-none placeholder:text-(--color-muted)"
        />
      </div>

      {filtered.length === 0 ? (
        <EmptyState icon={Search} message={tp("noResults")} />
      ) : (
        <Card variant="soft" className="overflow-hidden p-0">
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead>
                <tr className="text-[10.5px] font-bold uppercase tracking-wide text-(--color-muted)">
                  <th className="px-4 py-3 font-bold">{tp("openColumns.project")}</th>
                  <th className="px-4 py-3 font-bold">{tp("openColumns.skills")}</th>
                  <th className="px-4 py-3 font-bold">{tp("openColumns.priority")}</th>
                  <th className="px-4 py-3 font-bold">{tp("openColumns.deadline")}</th>
                  <th className="px-4 py-3 font-bold">{tp("openColumns.status")}</th>
                  <th className="px-4 py-3" />
                </tr>
              </thead>
              <tbody>
                {filtered.map((project) => {
                  const isJoining = joiningId === project.id;
                  const isPending = project.joinPending;
                  return (
                    <tr key={project.id} className="border-t border-(--border-neutral)">
                      <td className="px-4 py-3 font-semibold">{project.title}</td>
                      <td className="max-w-[14rem] px-4 py-3">
                        <div className="flex flex-wrap gap-1">
                          {project.skills.slice(0, 3).map((skill) => (
                            <Badge key={skill.id} variant="accent">
                              {skill.name}
                            </Badge>
                          ))}
                        </div>
                      </td>
                      <td className="px-4 py-3 text-(--color-muted)">{project.priorityLabel ?? "—"}</td>
                      <td className="px-4 py-3 text-xs text-(--color-muted)">
                        {project.deadline ? new Date(project.deadline).toLocaleDateString(undefined, { day: "numeric", month: "short" }) : "—"}
                      </td>
                      <td className="px-4 py-3">
                        <Badge variant="accent">{project.statusLabel}</Badge>
                      </td>
                      <td className="px-4 py-3 text-right">
                        {isPending ? (
                          <span className="inline-flex items-center gap-1.5 text-xs font-semibold text-(--color-muted)">
                            <Clock size={13} aria-hidden="true" />
                            {t("pending")}
                          </span>
                        ) : (
                          <button
                            type="button"
                            onClick={() => handleJoin(project.id)}
                            disabled={isJoining}
                            aria-busy={isJoining}
                            className="inline-flex items-center gap-1.5 rounded-full bg-brand-primary px-3 py-1.5 text-xs font-bold text-(--color-on-brand-primary) transition hover:opacity-90 disabled:opacity-60"
                          >
                            {isJoining && <Loader2 size={13} className="animate-spin" aria-hidden="true" />}
                            {isJoining ? t("joining") : t("joinCta")}
                          </button>
                        )}
                        {errorId === project.id && <p className="mt-1 text-right text-[11px] text-danger">{t("joinError")}</p>}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </Card>
      )}
    </div>
  );
}
