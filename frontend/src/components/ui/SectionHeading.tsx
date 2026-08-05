import type { ComponentProps, ReactNode } from "react";
import { Link } from "@/i18n/navigation";
import { ButtonLink } from "./ButtonLink";

interface SectionHeadingProps {
  id?: string;
  title: string;
  viewAllHref?: ComponentProps<typeof Link>["href"];
  viewAllLabel?: string;
  action?: ReactNode;
  className?: string;
}

/** En-tête de section standard (titre + lien "voir tout" optionnel) — factorise le bloc répété sur le dashboard et les pages de détail. */
export function SectionHeading({
  id,
  title,
  viewAllHref,
  viewAllLabel,
  action,
  className,
}: SectionHeadingProps) {
  const trailing =
    action ??
    (viewAllHref && (
      <ButtonLink href={viewAllHref} variant="secondary" className="text-xs">
        {viewAllLabel}
      </ButtonLink>
    ));

  return (
    <div className={`mb-3 flex flex-wrap items-center justify-between gap-2 ${className ?? ""}`}>
      <h2 id={id} className="text-lg font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
        {title}
      </h2>
      {trailing}
    </div>
  );
}
