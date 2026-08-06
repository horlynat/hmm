import clsx from "clsx";
import type { ComponentProps } from "react";
import { Link } from "@/i18n/navigation";

interface ButtonLinkProps extends ComponentProps<typeof Link> {
  variant?: "primary" | "secondary";
}

export function ButtonLink({
  variant = "primary",
  className,
  ...props
}: ButtonLinkProps) {
  return (
    <Link
      className={clsx(
        variant === "primary" ? "btn-primary" : "btn-secondary",
        className,
      )}
      {...props}
    />
  );
}
