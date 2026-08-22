import type { ReactNode } from "react";
import clsx from "clsx";

export function OptionCard({
  selected,
  onClick,
  icon,
  description,
  children,
}: {
  selected: boolean;
  onClick: () => void;
  icon?: string;
  description?: string;
  children: ReactNode;
}) {
  return (
    <button
      type="button"
      role="radio"
      aria-checked={selected}
      onClick={onClick}
      className={clsx(
        "flex items-start gap-2.5 rounded-[var(--radius-md)] border px-3.5 py-3 text-left text-sm font-semibold transition-all",
        selected
          ? "border-brand-primary bg-brand-primary/10 text-brand-primary shadow-sm"
          : "border-[var(--border-soft)] bg-bg-card hover:-translate-y-0.5 hover:border-brand-accent hover:shadow-sm",
      )}
    >
      {icon && (
        <span aria-hidden="true" className="text-lg leading-none">
          {icon}
        </span>
      )}
      <span className="flex flex-col gap-0.5">
        <span>{children}</span>
        {description && (
          <span className={clsx("text-xs font-normal", selected ? "text-brand-primary/80" : "opacity-60")}>
            {description}
          </span>
        )}
      </span>
    </button>
  );
}
