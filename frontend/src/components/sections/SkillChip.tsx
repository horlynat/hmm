import clsx from "clsx";
import type { Skill } from "@/lib/types";

export function SkillChip({ skill }: { skill: Skill }) {
  return (
    <div className="soft-card flex items-center justify-between gap-3 px-4 py-3">
      <span
        className="text-sm font-semibold"
        style={{ fontFamily: "var(--font-heading)" }}
      >
        {skill.name}
      </span>
      <div className="flex gap-0.5" aria-hidden="true">
        {Array.from({ length: 10 }).map((_, i) => (
          <span
            key={i}
            className={clsx(
              "h-1.5 w-1.5 rounded-full",
              i < skill.level ? "bg-brand-primary" : "bg-brand-dark/10",
            )}
          />
        ))}
      </div>
    </div>
  );
}
