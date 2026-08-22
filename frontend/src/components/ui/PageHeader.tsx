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

/**
 * En-tête standard des pages de l'espace compte : titre + sous-titre, icône
 * discrète en ligne (pas de badge coloré) + actions optionnelles à droite.
 * Volontairement plat — pas de carte, pas de bandeau teinté : c'est ce que
 * la maquette de refonte validée montre pour chaque topbar (un titre et un
 * sous-titre nus, l'identité visuelle vient du reste de la page).
 */
export function PageHeader({ icon: Icon, title, subtitle, tone = "default", actions, className }: PageHeaderProps) {
  return (
    <div className={clsx("flex flex-wrap items-end justify-between gap-4", className)}>
      <div className="flex items-center gap-2.5 min-w-0">
        {Icon && (
          <Icon
            size={20}
            aria-hidden="true"
            className={clsx("shrink-0", tone === "danger" ? "text-danger" : "text-brand-primary")}
          />
        )}
        <div className="min-w-0">
          <h1 className={clsx("text-[21px] font-extrabold tracking-tight", tone === "danger" && "text-danger")} style={{ fontFamily: "var(--font-heading)" }}>
            {title}
          </h1>
          {subtitle && <p className="mt-0.5 text-[12.5px] font-semibold text-(--color-muted)">{subtitle}</p>}
        </div>
      </div>
      {actions && <div className="flex shrink-0 flex-wrap items-center gap-2">{actions}</div>}
    </div>
  );
}
