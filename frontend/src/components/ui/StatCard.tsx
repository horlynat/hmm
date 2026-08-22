import clsx from "clsx";
import type { ComponentProps } from "react";
import type { LucideIcon } from "lucide-react";
import { Link } from "@/i18n/navigation";
import { Card } from "./Card";

interface StatCardProps {
  icon: LucideIcon;
  label: string;
  value: number | string;
  href?: ComponentProps<typeof Link>["href"];
  tone?: "default" | "warning" | "success" | "danger";
}

const TONE_ICON_CLASS: Record<NonNullable<StatCardProps["tone"]>, string> = {
  default: "text-brand-primary",
  warning: "text-(--color-badge-warning-text)",
  success: "text-success",
  danger: "text-danger",
};

/** Tuile de statistique compacte (libellé + valeur, icône discrète), avec lien optionnel. Réutilisée entre le dashboard et la gestion de projet. */
export function StatCard({ icon: Icon, label, value, href, tone = "default" }: StatCardProps) {
  const content = (
    <Card variant="soft" className="p-3 transition-colors duration-200 hover:border-brand-accent/30">
      <div className="flex items-center gap-1.5">
        <Icon size={13} aria-hidden="true" className={clsx("shrink-0", TONE_ICON_CLASS[tone])} />
        <span className="truncate text-[11px] font-bold uppercase tracking-wide text-(--color-muted)">{label}</span>
      </div>
      <div className="mt-1 text-xl font-extrabold leading-none" style={{ fontFamily: "var(--font-heading)" }}>
        {value}
      </div>
    </Card>
  );

  return href ? (
    <Link
      href={href}
      className="block rounded-[var(--radius-md)] transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-accent"
    >
      {content}
    </Link>
  ) : (
    content
  );
}
