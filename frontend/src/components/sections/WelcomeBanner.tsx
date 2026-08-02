"use client";

import { useEffect, useState } from "react";
import { X } from "lucide-react";

const AUTO_DISMISS_MS = 60_000;

/**
 * Bandeau de bienvenue éphémère, affiché uniquement juste après une connexion
 * (cf. `?welcome=1` pointé par LoginForm) — pas un élément permanent du
 * dashboard, le nom du client reste consultable dans son profil. Disparaît
 * seul après une minute, ou via le bouton de fermeture.
 */
export function WelcomeBanner({ message }: { message: string }) {
  const [visible, setVisible] = useState(true);

  useEffect(() => {
    // Retire ?welcome=1 de l'URL sans navigation : un rafraîchissement de la
    // page ne doit pas réafficher le bandeau.
    const url = new URL(window.location.href);
    url.searchParams.delete("welcome");
    window.history.replaceState(null, "", `${url.pathname}${url.search}`);

    const timer = window.setTimeout(() => setVisible(false), AUTO_DISMISS_MS);
    return () => window.clearTimeout(timer);
  }, []);

  if (!visible) return null;

  return (
    <div
      role="status"
      className="flex items-center justify-between gap-3 rounded-[var(--radius-md)] border border-(--brand-primary)/20 bg-(--brand-primary)/5 px-4 py-3 text-sm"
    >
      <span className="font-medium text-brand-primary">{message}</span>
      <button
        type="button"
        onClick={() => setVisible(false)}
        aria-label="×"
        className="shrink-0 opacity-60 transition-opacity hover:opacity-100"
      >
        <X size={16} aria-hidden="true" />
      </button>
    </div>
  );
}
