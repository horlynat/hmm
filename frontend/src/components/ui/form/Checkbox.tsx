"use client";

import { forwardRef, useId } from "react";
import type { InputHTMLAttributes, ReactNode } from "react";

interface CheckboxProps extends Omit<InputHTMLAttributes<HTMLInputElement>, "type"> {
  label: ReactNode;
  error?: string;
}

/** Case à cocher accessible : label cliquable, erreur liée par aria-describedby. */
export const Checkbox = forwardRef<HTMLInputElement, CheckboxProps>(
  function Checkbox({ label, error, id, ...props }, ref) {
    const generatedId = useId();
    const inputId = id ?? generatedId;
    const errorId = error ? `${inputId}-error` : undefined;

    return (
      <div>
        <label htmlFor={inputId} className="flex cursor-pointer items-start gap-2.5 text-sm">
          <input
            ref={ref}
            id={inputId}
            type="checkbox"
            className="mt-0.5 h-4 w-4 shrink-0 accent-brand-primary"
            aria-invalid={error ? true : undefined}
            aria-describedby={errorId}
            {...props}
          />
          <span>{label}</span>
        </label>
        {error && (
          <p id={errorId} role="alert" className="mt-1.5 text-xs font-medium text-danger">
            {error}
          </p>
        )}
      </div>
    );
  },
);
