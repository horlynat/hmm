import clsx from "clsx";
import type { HTMLAttributes } from "react";

interface BadgeProps extends HTMLAttributes<HTMLSpanElement> {
  variant?: "default" | "accent" | "outline";
}

export function Badge({ variant = "default", className, ...props }: BadgeProps) {
  const variantClass =
    variant === "accent"
      ? "badge-accent"
      : variant === "outline"
        ? "badge-outline"
        : "badge";

  return <span className={clsx(variantClass, className)} {...props} />;
}
