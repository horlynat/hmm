"use client";

import { forwardRef, useId } from "react";
import type { InputHTMLAttributes } from "react";
import clsx from "clsx";

interface TextInputProps extends InputHTMLAttributes<HTMLInputElement> {
  label: string;
  hint?: string;
  error?: string;
  /** Masque le label visuellement tout en le gardant pour les lecteurs d'écran. */
  hideLabel?: boolean;
}

/**
 * Champ texte accessible : label lié, `aria-invalid` et `aria-describedby`
 * pointant vers l'aide et/ou l'erreur, message d'erreur en `role="alert"`.
 * Compatible react-hook-form (`{...register("champ")}` transmet ref/onChange).
 */
export const TextInput = forwardRef<HTMLInputElement, TextInputProps>(
  function TextInput(
    { label, hint, error, hideLabel, id, className, ...props },
    ref,
  ) {
    const generatedId = useId();
    const inputId = id ?? generatedId;
    const hintId = hint ? `${inputId}-hint` : undefined;
    const errorId = error ? `${inputId}-error` : undefined;
    const describedBy = [hintId, errorId].filter(Boolean).join(" ") || undefined;

    return (
      <div className="mb-5">
        <label
          htmlFor={inputId}
          className={clsx("field-label", hideLabel && "sr-only")}
        >
          {label}
        </label>
        <input
          ref={ref}
          id={inputId}
          className={clsx("input", className)}
          aria-invalid={error ? true : undefined}
          aria-describedby={describedBy}
          {...props}
        />
        {hint && (
          <p id={hintId} className="mt-1.5 text-xs text-[var(--color-muted)]">
            {hint}
          </p>
        )}
        {error && (
          <p id={errorId} role="alert" className="mt-1.5 text-xs font-medium text-danger">
            {error}
          </p>
        )}
      </div>
    );
  },
);
