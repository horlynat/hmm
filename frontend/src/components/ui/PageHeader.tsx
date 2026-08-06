import clsx from "clsx";
import type { ReactNode } from "react";
import type { LucideIcon } from "lucide-react";

interface PageHeaderProps {
  icon?: LucideIcon;
  title: string;
  subtitle?: string;
  tone?: "default" | "danger";
  actions?: ReactNode;
  className?: string;
}

/** En-tête standard des pages de l'espace compte : icône + titre + sous-titre, actions optionnelles à droite. */
export function PageHeader({ icon: Icon, title, subtitle, tone = "default", actions, className }: PageHeaderProps) {
  return (
    <div className={clsx("flex flex-wrap items-start justify-between gap-4", className)}>
      <div className="flex items-start gap-3.5">
        {Icon && (
          <div
            className={clsx(
              "flex h-11 w-11 shrink-0 items-center justify-center rounded-xl",
              tone === "danger" ? "bg-danger/10 text-danger" : "bg-brand-primary/10 text-brand-primary",
            )}
          >
            <Icon size={21} aria-hidden="true" />
          </div>
        )}
        <div className="min-w-0">
          <h1 className={clsx("text-[clamp(1.5rem,2.8vw,2.05rem)]", tone === "danger" && "text-danger")}>{title}</h1>
          {subtitle && <p className="mt-1 text-sm text-(--color-muted)">{subtitle}</p>}
        </div>
      </div>
      {actions && <div className="flex shrink-0 flex-wrap items-center gap-2">{actions}</div>}
    </div>
  );
}
