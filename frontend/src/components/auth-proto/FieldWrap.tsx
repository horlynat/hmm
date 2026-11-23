import type { ReactNode } from "react";

/** Champ de formulaire du prototype (`.fl2`), avec son message d'erreur (`.e`). */
export function FieldWrap({ id, label, error, children }: { id: string; label: string; error?: string; children: ReactNode }) {
  return (
    <div className={error ? "fl2 err" : "fl2"} id={`f-${id}`}>
      <label htmlFor={id}>{label}</label>
      {children}
      <span className="e" role="alert">
        {error}
      </span>
    </div>
  );
}
