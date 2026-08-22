import type { ReactNode } from "react";
import { Inbox, type LucideIcon } from "lucide-react";

interface EmptyStateProps {
  icon?: LucideIcon;
  message: string;
  action?: ReactNode;
}

/**
 * État vide standard pour les listes du compte (projets, devis…) — évite une
 * ligne de texte nue perdue dans l'espace. Icône Lucide plutôt qu'un emoji
 * brut (rendu variable selon l'OS du visiteur, hors du langage d'icônes du
 * reste de l'appli — nav, StatCard, PageHeader n'utilisent que Lucide).
 */
export function EmptyState({ icon: Icon = Inbox, message, action }: EmptyStateProps) {
  return (
    <div className="flex flex-col items-center gap-3 rounded-[var(--radius-lg)] border border-dashed border-(--border-neutral) bg-(--color-surface-muted)/60 px-6 py-12 text-center">
      <span
        aria-hidden="true"
        className="flex h-12 w-12 items-center justify-center rounded-full bg-bg-card text-(--color-muted) shadow-sm"
      >
        <Icon size={22} aria-hidden="true" />
      </span>
      <p className="max-w-xs text-sm text-(--color-muted)">{message}</p>
      {action}
    </div>
  );
}
