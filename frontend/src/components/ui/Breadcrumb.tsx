import type { ComponentProps } from "react";
import { Link } from "@/i18n/navigation";

type BreadcrumbHref = ComponentProps<typeof Link>["href"];

interface BreadcrumbItem {
  label: string;
  href?: BreadcrumbHref;
}

/** Fil d'ariane minimal pour les pages de détail de l'espace compte (projet, devis). Le dernier élément n'est jamais un lien. */
export function Breadcrumb({ items }: { items: BreadcrumbItem[] }) {
  return (
    <nav aria-label="Breadcrumb" className="text-sm opacity-60">
      <ol className="flex flex-wrap items-center gap-1.5">
        {items.map((item, index) => {
          const isLast = index === items.length - 1;
          return (
            <li key={item.label} className="flex items-center gap-1.5">
              {item.href && !isLast ? (
                <Link href={item.href} className="hover:text-brand-primary hover:underline">
                  {item.label}
                </Link>
              ) : (
                <span aria-current={isLast ? "page" : undefined} className="truncate">
                  {item.label}
                </span>
              )}
              {!isLast && <span aria-hidden="true">/</span>}
            </li>
          );
        })}
      </ol>
    </nav>
  );
}
