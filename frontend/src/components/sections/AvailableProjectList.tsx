"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { CalendarClock, Clock, Loader2 } from "lucide-react";
import { Badge, Button, Card } from "@/components/ui";
import { FormMessage } from "@/components/ui/form";
import { joinProject } from "@/lib/auth/actions";
import type { AvailableProject } from "@/lib/types";

/**
 * Une carte par projet "à venir" disponible, avec bouton de demande
 * d'auto-association — contrairement à ProjectList (AccountLists.tsx), ce
 * n'est pas un simple lien vers /compte/gestion-projet/[id] : cliquer ne
 * donne PAS accès au projet, seulement une demande "en attente" qu'un admin
 * doit valider. La carte reste donc affichée (pas de retrait après clic,
 * contrairement à l'ancien comportement où "Rejoindre" donnait un accès
 * immédiat) — elle bascule sur un état "en attente" persistant.
 */
export function AvailableProjectList({ projects: initialProjects }: { projects: AvailableProject[] }) {
  const t = useTranslations("auth.availableProjects");
  const [projects, setProjects] = useState(initialProjects);
  const [joiningId, setJoiningId] = useState<number | null>(null);
  const [errorId, setErrorId] = useState<number | null>(null);

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
    return null;
  }

  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
      {projects.map((project) => {
        const isJoining = joiningId === project.id;
        const isPending = project.joinPending;
        return (
          <Card key={project.id} variant="soft" className="flex h-full flex-col gap-3 p-5">
            <div className="flex flex-wrap items-start justify-between gap-2">
              <h3 className="font-heading text-base font-bold text-(--brand-dark)">{project.title}</h3>
              {project.priorityLabel && <Badge variant="neutral">{project.priorityLabel}</Badge>}
            </div>

            {project.description && (
              <p className="line-clamp-3 text-sm opacity-75">{project.description}</p>
            )}

            {(project.skills.length > 0 || project.tags.length > 0) && (
              <div className="flex flex-wrap gap-1.5">
                {project.skills.map((skill) => (
                  <Badge key={`skill-${skill.id}`} variant="accent">
                    {skill.name}
                  </Badge>
                ))}
                {project.tags.map((tag) => (
                  <Badge key={`tag-${tag.id}`} variant="outline">
                    {tag.name}
                  </Badge>
                ))}
              </div>
            )}

            {project.deadline && (
              <p className="flex items-center gap-1.5 text-xs opacity-60">
                <CalendarClock size={14} aria-hidden="true" />
                {t("deadline", { date: new Date(project.deadline).toLocaleDateString() })}
              </p>
            )}

            <div className="mt-auto pt-2">
              {isPending ? (
                <div className="flex items-center justify-center gap-2 rounded-[var(--radius-sm)] border border-(--border-softer) bg-(--color-surface-muted) py-2.5 text-sm font-semibold text-(--color-muted)">
                  <Clock size={16} aria-hidden="true" />
                  {t("pending")}
                </div>
              ) : (
                <Button
                  onClick={() => handleJoin(project.id)}
                  disabled={isJoining}
                  aria-busy={isJoining}
                  className="w-full"
                >
                  {isJoining && <Loader2 size={16} className="animate-spin" aria-hidden="true" />}
                  {isJoining ? t("joining") : t("joinCta")}
                </Button>
              )}
              {errorId === project.id && (
                <div className="mt-2">
                  <FormMessage variant="error">{t("joinError")}</FormMessage>
                </div>
              )}
            </div>
          </Card>
        );
      })}
    </div>
  );
}
