"use client";

import type { ButtonHTMLAttributes, ReactNode } from "react";
import clsx from "clsx";

interface SubmitButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  pending?: boolean;
  /** Texte annoncé aux lecteurs d'écran pendant l'envoi (sinon « Envoi en cours »). */
  pendingLabel?: string;
  children: ReactNode;
  variant?: "primary" | "secondary";
}

function Spinner() {
  return (
    <svg
      className="h-4 w-4 animate-spin"
      viewBox="0 0 24 24"
      fill="none"
      aria-hidden="true"
    >
      <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
      <path
        className="opacity-90"
        fill="currentColor"
        d="M4 12a8 8 0 0 1 8-8V0C5.4 0 0 5.4 0 12h4z"
      />
    </svg>
  );
}

/**
 * Bouton de soumission avec état de chargement accessible :
 * `aria-busy`, désactivation, spinner décoratif + libellé annoncé.
 */
export function SubmitButton({
  pending = false,
  pendingLabel,
  children,
  className,
  variant = "primary",
  disabled,
  ...props
}: SubmitButtonProps) {
  return (
    <button
      type="submit"
      aria-busy={pending}
      disabled={pending || disabled}
      className={clsx(variant === "primary" ? "btn-primary" : "btn-secondary", className)}
      {...props}
    >
      {pending ? (
        <>
          <Spinner />
          <span>{pendingLabel ?? "…"}</span>
        </>
      ) : (
        children
      )}
    </button>
  );
}
