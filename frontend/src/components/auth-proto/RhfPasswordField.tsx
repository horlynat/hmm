"use client";

import { useState } from "react";
import type { FieldValues, Path, UseFormRegister } from "react-hook-form";
import { useTranslations } from "next-intl";
import { scorePassword } from "@/lib/passwordStrength";
import { PasswordVisibilityToggle } from "./PasswordVisibilityToggle";

/**
 * Champ mot de passe d'un formulaire react-hook-form (`.pwd`), avec
 * indicateur de robustesse optionnel (`.meter`) basé sur la valeur
 * surveillée via `useWatch` — même motif que `FreelanceForm` existant.
 */
export function RhfPasswordField<T extends FieldValues>({
  id,
  label,
  error,
  register,
  name,
  watched,
  withMeter,
  meterHint,
}: {
  id: string;
  label: string;
  error?: string;
  register: UseFormRegister<T>;
  name: Path<T>;
  watched?: string;
  withMeter?: boolean;
  meterHint?: string;
}) {
  const t = useTranslations("authPage");
  const [visible, setVisible] = useState(false);
  const score = withMeter ? scorePassword(watched ?? "") : 0;

  return (
    <div className={error ? "fl2 err" : "fl2"} id={`f-${id}`}>
      <label htmlFor={id}>{label}</label>
      <div className="pwd">
        <input id={id} type={visible ? "text" : "password"} className="fi" autoComplete="new-password" aria-invalid={error ? true : undefined} {...register(name)} />
        <PasswordVisibilityToggle visible={visible} onToggle={() => setVisible((v) => !v)} />
      </div>
      {withMeter && watched && (
        <div className="meter" data-s={score} aria-live="polite">
          <i />
          <span>{meterHint ?? t("passwordMeterHint")}</span>
        </div>
      )}
      <span className="e" role="alert">
        {error}
      </span>
    </div>
  );
}
