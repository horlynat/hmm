import clsx from "clsx";
import type { ReactNode } from "react";

/** Conteneur plat neutre regroupant une ou plusieurs `SettingsSection` (espace compte, style GitHub Settings). */
export function SettingsSectionGroup({
  children,
  className,
}: {
  children: ReactNode;
  className?: string;
}) {
  return (
    <div
      className={clsx(
        "divide-y divide-(--border-neutral) overflow-hidden rounded-md border border-(--border-neutral) bg-bg-card",
        className,
      )}
    >
      {children}
    </div>
  );
}

interface SettingsSectionProps {
  title?: string;
  description?: string;
  children: ReactNode;
  tone?: "default" | "danger";
  layout?: "stacked" | "row";
  className?: string;
}

/** Une ligne d'une `SettingsSectionGroup` : en-tête (titre + description) optionnel, puis contenu. */
export function SettingsSection({
  title,
  description,
  children,
  tone = "default",
  layout = "stacked",
  className,
}: SettingsSectionProps) {
  const hasHeader = Boolean(title || description);

  return (
    <div
      className={clsx(
        "p-5 sm:p-6",
        layout === "row" && "sm:flex sm:items-center sm:justify-between sm:gap-4",
        className,
      )}
    >
      {hasHeader && (
        <div className={layout === "row" ? "sm:flex-1" : undefined}>
          {title && (
            <h2
              className={clsx(
                "text-sm font-semibold",
                tone === "danger" ? "text-danger" : "text-brand-dark",
              )}
            >
              {title}
            </h2>
          )}
          {description && (
            <p className="mt-1 text-sm text-(--color-muted)">{description}</p>
          )}
        </div>
      )}
      <div
        className={clsx(
          hasHeader && "mt-4",
          hasHeader && layout === "row" && "sm:mt-0 sm:shrink-0",
        )}
      >
        {children}
      </div>
    </div>
  );
}
