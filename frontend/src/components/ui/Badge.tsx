import clsx from "clsx";
import type { HTMLAttributes } from "react";

interface BadgeProps extends HTMLAttributes<HTMLSpanElement> {
  variant?: "default" | "accent" | "outline" | "neutral" | "success" | "warning" | "danger" | "info";
}

const VARIANT_CLASS: Record<NonNullable<BadgeProps["variant"]>, string> = {
  default: "badge",
  accent: "badge-accent",
  outline: "badge-outline",
  neutral: "badge-neutral",
  success: "badge-success",
  warning: "badge-warning",
  danger: "badge-danger",
  info: "badge-info",
};

export function Badge({ variant = "default", className, ...props }: BadgeProps) {
  return <span className={clsx(VARIANT_CLASS[variant], className)} {...props} />;
}
