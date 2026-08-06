import type { ReactNode } from "react";

interface EmptyStateProps {
  icon?: string;
  message: string;
  action?: ReactNode;
}

/** État vide standard pour les listes du compte (projets, devis…) — évite une ligne de texte nue perdue dans l'espace. */
export function EmptyState({ icon = "📭", message, action }: EmptyStateProps) {
  return (
    <div className="flex flex-col items-center gap-3 rounded-[var(--radius-lg)] border border-dashed border-(--border-neutral) bg-(--color-surface-muted)/60 px-6 py-12 text-center">
      <span
        aria-hidden="true"
        className="flex h-12 w-12 items-center justify-center rounded-full bg-bg-card text-2xl shadow-sm"
      >
        {icon}
      </span>
      <p className="max-w-xs text-sm text-(--color-muted)">{message}</p>
      {action}
    </div>
  );
}
