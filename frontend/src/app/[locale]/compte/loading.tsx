import { Skeleton } from "@/components/ui";

/**
 * Fallback de chargement générique de l'espace compte — sert de secours pour
 * toutes les routes sans loading.tsx dédié (réglages, détail projet/devis…),
 * en plus du dashboard lui-même. Reste volontairement neutre (pas de grille
 * de stats) pour rester plausible sur un formulaire de réglages comme sur le
 * tableau de bord.
 */
export default function Loading() {
  return (
    <div className="max-w-160 space-y-6">
      <div className="flex items-center gap-3.5">
        <Skeleton className="h-11 w-11 shrink-0 rounded-xl" />
        <div className="flex-1 space-y-2">
          <Skeleton className="h-7 w-52" />
          <Skeleton className="h-4 w-72" />
        </div>
      </div>

      <Skeleton className="h-40 rounded-[var(--radius-lg)]" />
      <Skeleton className="h-24 rounded-[var(--radius-lg)]" />
    </div>
  );
}
