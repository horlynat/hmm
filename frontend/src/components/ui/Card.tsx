import clsx from "clsx";
import type { HTMLAttributes } from "react";

interface CardProps extends HTMLAttributes<HTMLDivElement> {
  variant?: "default" | "soft";
}

export function Card({ variant = "default", className, ...props }: CardProps) {
  return (
    <div
      className={clsx(variant === "soft" ? "soft-card" : "card", className)}
      {...props}
    />
  );
}
