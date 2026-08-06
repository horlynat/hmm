import type { ReactNode } from "react";
import clsx from "clsx";

interface FormSectionProps {
  icon: string;
  title: string;
  description?: string;
  children: ReactNode;
  className?: string;
}

/** En-tête de regroupement de champs (icône + titre) — structure les formulaires longs en blocs lisibles. */
export function FormSection({ icon, title, description, children, className }: FormSectionProps) {
  return (
    <div className={clsx("mb-6", className)}>
      <div className="mb-3.5 flex items-center gap-2.5">
        <span
          aria-hidden="true"
          className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-primary/10 text-base"
        >
          {icon}
        </span>
        <div>
          <div className="text-sm font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
            {title}
          </div>
          {description && <div className="text-xs text-[var(--color-muted)]">{description}</div>}
        </div>
      </div>
      {children}
    </div>
  );
}
