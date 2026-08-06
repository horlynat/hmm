"use client";

import { useState, useTransition, type FormEvent } from "react";
import { Clock, Timer } from "lucide-react";
import clsx from "clsx";
import { Card } from "@/components/ui";
import { SubmitButton, FormMessage } from "@/components/ui/form";
import { logTime } from "@/lib/auth/actions";
import type { SessionTask, SessionTimeTracking } from "@/lib/types";

interface ProjectTimeTrackingProps {
  projectId: number;
  initialData: SessionTimeTracking;
  tasks: SessionTask[];
  readOnly: boolean;
  labels: {
    totalLabel: string;
    mineLabel: string;
    historyEmpty: string;
    formTitle: string;
    hoursLabel: string;
    minutesLabel: string;
    descriptionLabel: string;
    dateLabel: string;
    taskLabel: string;
    noTask: string;
    submit: string;
    submitting: string;
    success: string;
  };
}

function SummaryTile({
  icon: Icon,
  tone,
  label,
  value,
}: {
  icon: typeof Clock;
  tone: "default" | "success";
  label: string;
  value: string;
}) {
  return (
    <Card variant="soft" className="flex items-center gap-3.5 p-4">
      <div
        className={clsx(
          "flex h-11 w-11 shrink-0 items-center justify-center rounded-xl",
          tone === "success" ? "bg-success/15 text-success" : "bg-brand-primary/10 text-brand-primary",
        )}
      >
        <Icon size={19} aria-hidden="true" />
      </div>
      <div className="min-w-0">
        <p className="text-xs font-medium text-(--color-muted)">{label}</p>
        <p className="truncate text-xl font-bold leading-tight" style={{ fontFamily: "var(--font-heading)" }}>
          {value}
        </p>
      </div>
    </Card>
  );
}

/** Suivi du temps passé sur un projet : historique visible par toute l'équipe, saisie en self-service (masquée si readOnly). */
export function ProjectTimeTracking({ projectId, initialData, tasks, readOnly, labels }: ProjectTimeTrackingProps) {
  const [data, setData] = useState(initialData);
  const [hours, setHours] = useState("");
  const [minutes, setMinutes] = useState("");
  const [spentOn, setSpentOn] = useState(() => new Date().toISOString().slice(0, 10));
  const [description, setDescription] = useState("");
  const [taskId, setTaskId] = useState("");
  const [status, setStatus] = useState<"idle" | "success" | "error">("idle");
  const [error, setError] = useState("");
  const [isPending, startTransition] = useTransition();

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const totalMinutes = (Number(hours) || 0) * 60 + (Number(minutes) || 0);
    if (totalMinutes <= 0) return;

    setStatus("idle");
    startTransition(async () => {
      const result = await logTime(projectId, {
        minutes: totalMinutes,
        spentOn: spentOn || undefined,
        description: description.trim() || undefined,
        taskId: taskId ? Number(taskId) : undefined,
      });

      if (result.ok && result.entry) {
        setData((prev) => ({
          entries: [result.entry!, ...prev.entries],
          totalMinutes: prev.totalMinutes + totalMinutes,
          formattedTotalTime: prev.formattedTotalTime,
          mineMinutes: prev.mineMinutes + totalMinutes,
          mineFormattedTime: prev.mineFormattedTime,
        }));
        setHours("");
        setMinutes("");
        setDescription("");
        setTaskId("");
        setStatus("success");
      } else {
        setError(result.ok ? "" : result.error);
        setStatus("error");
      }
    });
  }

  return (
    <div className="space-y-3">
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <SummaryTile icon={Clock} tone="default" label={labels.totalLabel} value={data.formattedTotalTime} />
        <SummaryTile icon={Timer} tone="success" label={labels.mineLabel} value={data.mineFormattedTime} />
      </div>

      {!readOnly && (
        <Card variant="soft" className="p-5">
          <p className="mb-4 text-sm font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
            {labels.formTitle}
          </p>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
              <div>
                <label className="field-label" htmlFor="time-hours">{labels.hoursLabel}</label>
                <input
                  id="time-hours"
                  type="number"
                  min={0}
                  max={24}
                  placeholder="0"
                  className="input"
                  value={hours}
                  onChange={(e) => setHours(e.target.value)}
                />
              </div>
              <div>
                <label className="field-label" htmlFor="time-minutes">{labels.minutesLabel}</label>
                <input
                  id="time-minutes"
                  type="number"
                  min={0}
                  max={59}
                  placeholder="0"
                  className="input"
                  value={minutes}
                  onChange={(e) => setMinutes(e.target.value)}
                />
              </div>
              <div className="col-span-2">
                <label className="field-label" htmlFor="time-date">{labels.dateLabel}</label>
                <input
                  id="time-date"
                  type="date"
                  className="input"
                  value={spentOn}
                  onChange={(e) => setSpentOn(e.target.value)}
                />
              </div>
            </div>

            {tasks.length > 0 && (
              <div>
                <label className="field-label" htmlFor="time-task">{labels.taskLabel}</label>
                <select id="time-task" className="input" value={taskId} onChange={(e) => setTaskId(e.target.value)}>
                  <option value="">{labels.noTask}</option>
                  {tasks.map((task) => (
                    <option key={task.id} value={task.id}>
                      {task.title}
                    </option>
                  ))}
                </select>
              </div>
            )}

            <div>
              <label className="field-label" htmlFor="time-description">{labels.descriptionLabel}</label>
              <textarea
                id="time-description"
                className="input resize-none"
                rows={2}
                value={description}
                onChange={(e) => setDescription(e.target.value)}
              />
            </div>

            <SubmitButton pending={isPending} pendingLabel={labels.submitting} className="w-full sm:w-auto">
              {labels.submit}
            </SubmitButton>

            {status === "success" && <FormMessage variant="success">{labels.success}</FormMessage>}
            {status === "error" && <FormMessage variant="error">{error}</FormMessage>}
          </form>
        </Card>
      )}

      {data.entries.length === 0 ? (
        <Card variant="soft" className="p-6 text-center text-sm opacity-60">
          {labels.historyEmpty}
        </Card>
      ) : (
        <Card variant="soft" className="divide-y divide-(--border-neutral) overflow-hidden p-0">
          {data.entries.map((entry) => (
            <div key={entry.id} className="flex items-center gap-3 p-4">
              <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-(--color-surface-muted) text-brand-primary">
                <Clock size={15} aria-hidden="true" />
              </span>
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-semibold">
                  {entry.user.fullName}
                  {entry.task && <span className="font-normal text-(--color-muted)"> — {entry.task.title}</span>}
                </p>
                <p className="truncate text-xs text-(--color-muted)">
                  {entry.description || new Date(entry.spentOn).toLocaleDateString()}
                </p>
              </div>
              <span className="shrink-0 text-sm font-bold text-brand-dark" style={{ fontFamily: "var(--font-heading)" }}>
                {entry.formattedDuration}
              </span>
            </div>
          ))}
        </Card>
      )}
    </div>
  );
}
