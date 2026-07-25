import clsx from "clsx";
import type { ButtonHTMLAttributes } from "react";

interface ChipProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  active?: boolean;
}

export function Chip({ active, className, type = "button", ...props }: ChipProps) {
  return (
    <button
      type={type}
      className={clsx(
        "rounded-full border px-3.5 py-2 font-mono text-xs transition-colors",
        active
          ? "border-brand-primary bg-brand-primary text-white"
          : "border-[var(--border-soft)] bg-bg-card text-brand-primary",
        className,
      )}
      {...props}
    />
  );
}
