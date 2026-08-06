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

const TONE_CLASS: Record<NonNullable<StatCardProps["tone"]>, string> = {
  default: "bg-brand-primary/10 text-brand-primary",
  warning: "bg-warning/15 text-(--color-badge-warning-text)",
  success: "bg-success/15 text-success",
  danger: "bg-danger/10 text-danger",
};

/** Tuile de statistique premium (icône + valeur + libellé), avec lien optionnel. Réutilisée entre le dashboard et la gestion de projet. */
export function StatCard({ icon: Icon, label, value, href, tone = "default" }: StatCardProps) {
  const content = (
    <Card
      variant="soft"
      className="group flex items-center gap-3.5 p-4 transition-all duration-200 hover:border-brand-accent/30 hover:shadow-md"
    >
      <div
        className={clsx(
          "flex h-11 w-11 shrink-0 items-center justify-center rounded-xl transition-transform duration-200",
          href && "group-hover:scale-105",
          TONE_CLASS[tone],
        )}
      >
        <Icon size={19} aria-hidden="true" />
      </div>
      <div className="min-w-0">
        <div className="text-2xl font-bold leading-none" style={{ fontFamily: "var(--font-heading)" }}>
          {value}
        </div>
        <div className="mt-1.5 line-clamp-2 text-xs font-medium text-(--color-muted)">{label}</div>
      </div>
    </Card>
  );

  return href ? (
    <Link
      href={href}
      className="block rounded-[var(--radius-lg)] transition-transform duration-200 hover:-translate-y-0.5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-accent"
    >
      {content}
    </Link>
  ) : (
    content
  );
}
