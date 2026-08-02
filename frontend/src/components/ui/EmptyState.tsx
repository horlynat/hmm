interface EmptyStateProps {
  icon?: string;
  message: string;
}

/** État vide standard pour les listes du compte (projets, devis…) — évite une ligne de texte nue perdue dans l'espace. */
export function EmptyState({ icon = "📭", message }: EmptyStateProps) {
  return (
    <div className="flex flex-col items-center gap-2 rounded-[var(--radius-md)] border border-dashed border-[var(--border-soft)] bg-bg-card/50 px-6 py-10 text-center">
      <span aria-hidden="true" className="text-2xl">
        {icon}
      </span>
      <p className="text-sm opacity-60">{message}</p>
    </div>
  );
}
