import clsx from "clsx";
import type { ReactNode } from "react";
import type { LucideIcon } from "lucide-react";

interface AlertProps {
  variant?: "info" | "warning" | "success" | "danger";
  icon?: LucideIcon;
  title?: string;
  children: ReactNode;
  action?: ReactNode;
  className?: string;
}

const VARIANT_CLASS: Record<NonNullable<AlertProps["variant"]>, string> = {
  info: "border-brand-primary bg-brand-primary/5",
  warning: "border-warning bg-warning/10",
  success: "border-success bg-success/10",
  danger: "border-danger bg-danger/10",
};

const ICON_CLASS: Record<NonNullable<AlertProps["variant"]>, string> = {
  info: "text-brand-primary",
  warning: "text-(--color-badge-warning-text)",
  success: "text-success",
  danger: "text-danger",
};

/** Bannière d'alerte/notice générique (bordure gauche + fond léger par variante) — remplace le pattern "Card + bordure en dur" pour les nouveaux usages. */
export function Alert({ variant = "info", icon: Icon, title, children, action, className }: AlertProps) {
  return (
    <div
      role="alert"
      className={clsx("rounded-[var(--radius-lg)] border-l-4 p-4", VARIANT_CLASS[variant], className)}
    >
      <div className="flex flex-col gap-3 sm:flex-row sm:items-start">
        <div className="flex min-w-0 flex-1 items-start gap-3">
          {Icon && (
            <div className={clsx("mt-0.5 shrink-0", ICON_CLASS[variant])}>
              <Icon size={18} aria-hidden="true" />
            </div>
          )}
          <div className="min-w-0 flex-1">
            {title && <p className="mb-1 text-sm font-semibold">{title}</p>}
            <div className="text-sm text-(--color-muted)">{children}</div>
          </div>
        </div>
        {action && <div className="shrink-0 sm:pl-1">{action}</div>}
      </div>
    </div>
  );
}
