import type { ComponentProps } from "react";
import { ChevronRight } from "lucide-react";
import { Link } from "@/i18n/navigation";

type BreadcrumbHref = ComponentProps<typeof Link>["href"];

interface BreadcrumbItem {
  label: string;
  href?: BreadcrumbHref;
}

/** Fil d'ariane minimal pour les pages de détail de l'espace compte (projet, devis). Le dernier élément n'est jamais un lien. */
export function Breadcrumb({ items }: { items: BreadcrumbItem[] }) {
  return (
    <nav aria-label="Breadcrumb" className="text-sm text-(--color-muted)">
      <ol className="flex flex-wrap items-center gap-1.5">
        {items.map((item, index) => {
          const isLast = index === items.length - 1;
          return (
            <li key={item.label} className="flex items-center gap-1.5">
              {item.href && !isLast ? (
                <Link href={item.href} className="font-medium transition-colors hover:text-brand-primary">
                  {item.label}
                </Link>
              ) : (
                <span aria-current={isLast ? "page" : undefined} className="truncate font-medium text-brand-dark">
                  {item.label}
                </span>
              )}
              {!isLast && <ChevronRight size={14} aria-hidden="true" className="shrink-0 opacity-40" />}
            </li>
          );
        })}
      </ol>
    </nav>
  );
}
