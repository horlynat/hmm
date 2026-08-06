"use client";

import { useState, useTransition } from "react";
import { CheckCircle2, Circle, CircleDashed, CircleSlash } from "lucide-react";
import clsx from "clsx";
import { Badge, Card } from "@/components/ui";
import { updateTaskStatus } from "@/lib/auth/actions";
import type { SessionTask, TaskStatus } from "@/lib/types";

const STATUS_ORDER: TaskStatus[] = ["todo", "in_progress", "done", "blocked"];

const STATUS_ICON: Record<TaskStatus, typeof Circle> = {
  todo: CircleDashed,
  in_progress: Circle,
  done: CheckCircle2,
  blocked: CircleSlash,
};

const STATUS_ICON_TONE: Record<TaskStatus, string> = {
  todo: "bg-(--color-surface-muted) text-(--color-muted)",
  in_progress: "bg-brand-primary/10 text-brand-primary",
  done: "bg-success/15 text-success",
  blocked: "bg-danger/10 text-danger",
};

interface ProjectTaskBoardProps {
  projectId: number;
  initialTasks: SessionTask[];
  readOnly: boolean;
  labels: {
    empty: string;
    notAssigned: string;
    statusLabels: Record<TaskStatus, string>;
    updateError: string;
    dueLabel: string;
    overdueLabel: string;
  };
}

/** Liste des tâches d'un projet — statut modifiable en self-service uniquement pour les tâches assignées à l'utilisateur courant. */
export function ProjectTaskBoard({ projectId, initialTasks, readOnly, labels }: ProjectTaskBoardProps) {
  const [tasks, setTasks] = useState(initialTasks);
  const [pendingId, setPendingId] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [, startTransition] = useTransition();

  function handleStatusChange(taskId: number, status: TaskStatus) {
    setError(null);
    setPendingId(taskId);
    startTransition(async () => {
      const result = await updateTaskStatus(projectId, taskId, status);
      setPendingId(null);
      if (result.ok && result.task) {
        setTasks((prev) => prev.map((t) => (t.id === taskId ? result.task! : t)));
      } else {
        setError(result.ok ? null : result.error);
      }
    });
  }

  if (tasks.length === 0) {
    return (
      <Card variant="soft" className="p-6 text-center text-sm opacity-60">
        {labels.empty}
      </Card>
    );
  }

  return (
    <div className="flex flex-col gap-2">
      <Card variant="soft" className="divide-y divide-(--border-neutral) overflow-hidden p-0">
        {tasks.map((task) => {
          const Icon = STATUS_ICON[task.status];
          const editable = task.isMine && !readOnly;

          return (
            <div key={task.id} className="flex flex-col gap-3 p-4 lg:flex-row lg:items-center">
              <div className="flex min-w-0 flex-1 items-center gap-3.5">
                <span className={clsx("flex h-9 w-9 shrink-0 items-center justify-center rounded-full", STATUS_ICON_TONE[task.status])}>
                  <Icon size={17} aria-hidden="true" />
                </span>
                <div className="min-w-0 flex-1">
                  <p className="truncate text-sm font-semibold">{task.title}</p>
                  {task.description && <p className="mt-0.5 truncate text-xs text-(--color-muted)">{task.description}</p>}
                  <div className="mt-1.5 flex flex-wrap items-center gap-1.5">
                    {task.dueDate && (
                      <Badge variant={task.isOverdue ? "danger" : "neutral"}>
                        {task.isOverdue ? labels.overdueLabel : labels.dueLabel}: {new Date(task.dueDate).toLocaleDateString()}
                      </Badge>
                    )}
                    {!task.assignee && <span className="text-xs text-(--color-muted)">{labels.notAssigned}</span>}
                    {!task.isMine && task.assignee && (
                      <span className="text-xs text-(--color-muted)">{task.assignee.fullName}</span>
                    )}
                  </div>
                </div>
              </div>

              {editable ? (
                <div className="flex shrink-0 flex-wrap gap-1.5 pl-12 lg:pl-0">
                  {STATUS_ORDER.map((status) => {
                    const active = status === task.status;
                    return (
                      <button
                        key={status}
                        type="button"
                        disabled={pendingId === task.id}
                        onClick={() => handleStatusChange(task.id, status)}
                        className={clsx(
                          "rounded-full border px-3 py-1.5 font-mono text-xs transition-colors disabled:opacity-50",
                          active
                            ? "border-brand-primary bg-brand-primary text-(--color-on-brand-primary)"
                            : "border-(--border-neutral) bg-bg-card text-(--color-muted) hover:text-brand-primary",
                        )}
                      >
                        {labels.statusLabels[status]}
                      </button>
                    );
                  })}
                </div>
              ) : (
                <Badge variant={task.statusVariant as never} className="w-fit shrink-0 self-start lg:self-center">
                  {task.statusLabel}
                </Badge>
              )}
            </div>
          );
        })}
      </Card>
      {error && <p className="text-xs text-danger">{labels.updateError}</p>}
    </div>
  );
}
