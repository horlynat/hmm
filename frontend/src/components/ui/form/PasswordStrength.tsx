"use client";

import { useTranslations } from "next-intl";
import clsx from "clsx";
import { scorePassword } from "@/lib/passwordStrength";

/** Indicateur visuel de robustesse d'un mot de passe, sous forme de 4 segments colorés. */
export function PasswordStrength({ value }: { value: string }) {
  const tv = useTranslations("validation");
  if (!value) return null;

  const score = scorePassword(value);
  const complete = score >= 5;
  const filled = Math.min(4, score);
  const color = complete
    ? "bg-success"
    : filled <= 1
      ? "bg-danger"
      : filled <= 2
        ? "bg-warning"
        : "bg-brand-accent";
  const label = complete
    ? tv("passwordStrengthStrong")
    : filled <= 1
      ? tv("passwordStrengthWeak")
      : filled <= 2
        ? tv("passwordStrengthFair")
        : tv("passwordStrengthGood");

  return (
    <div className="-mt-3 mb-5" aria-live="polite">
      <div className="mb-1.5 flex gap-1">
        {Array.from({ length: 4 }).map((_, i) => (
          <div
            key={i}
            className={clsx("h-1 flex-1 rounded-full transition-colors", i < filled ? color : "bg-[var(--border-soft)]")}
          />
        ))}
      </div>
      <p className="text-xs text-[var(--color-muted)]">
        {tv("passwordStrengthLabel")} : <span className="font-semibold">{label}</span>
      </p>
    </div>
  );
}
