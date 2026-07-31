import type { ReactNode } from "react";
import clsx from "clsx";

interface FormMessageProps {
  variant: "success" | "error";
  children: ReactNode;
  className?: string;
}

/**
 * Message de résultat de formulaire annoncé aux lecteurs d'écran :
 * `role="alert"` (assertif) pour une erreur, `role="status"` (poli) pour un succès.
 */
export function FormMessage({ variant, children, className }: FormMessageProps) {
  return (
    <p
      role={variant === "error" ? "alert" : "status"}
      aria-live={variant === "error" ? "assertive" : "polite"}
      className={clsx(
        "mt-3 text-center text-sm font-medium",
        variant === "success" ? "text-success" : "text-danger",
        className,
      )}
    >
      {variant === "success" && <span aria-hidden="true">✓ </span>}
      {children}
    </p>
  );
}
