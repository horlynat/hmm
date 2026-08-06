import type { ReactNode } from "react";
import { Link } from "@/i18n/navigation";
import type { NavHref } from "@/config/site";

/**
 * Lien vers une page légale, utilisé dans le texte enrichi (t.rich) des
 * cases à cocher de consentement. `stopPropagation` évite qu'un clic sur le
 * lien ne coche/décoche aussi la case (le lien est imbriqué dans le <label>).
 */
export function LegalLink({ href, children }: { href: NavHref; children: ReactNode }) {
  return (
    <Link
      href={href}
      className="font-semibold text-brand-primary hover:underline"
      onClick={(e) => e.stopPropagation()}
    >
      {children}
    </Link>
  );
}
