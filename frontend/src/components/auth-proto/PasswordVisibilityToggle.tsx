"use client";

import { useTranslations } from "next-intl";

/** Bouton afficher/masquer d'un champ mot de passe (`.pwd .ib`), partagé entre les variantes contrôlée et react-hook-form. */
export function PasswordVisibilityToggle({ visible, onToggle }: { visible: boolean; onToggle: () => void }) {
  const t = useTranslations("authPage");
  return (
    <button type="button" className="ib" aria-label={visible ? t("hidePassword") : t("showPassword")} onClick={onToggle}>
      {visible ? (
        <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <path d="M3 3l18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 4.2A9.8 9.8 0 0 1 12 4c5 0 9 4.5 10 8-.4 1.3-1.1 2.6-2.1 3.8M6.1 6.1C4 7.5 2.6 9.6 2 12c1 3.5 5 8 10 8 1.9 0 3.6-.6 5-1.5" />
        </svg>
      ) : (
        <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <path d="M2 12c1-3.5 5-8 10-8s9 4.5 10 8c-1 3.5-5 8-10 8S3 15.5 2 12z" />
          <circle cx="12" cy="12" r="3" />
        </svg>
      )}
    </button>
  );
}
