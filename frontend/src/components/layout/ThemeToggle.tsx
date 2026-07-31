"use client";

import { useSyncExternalStore } from "react";
import { useTranslations } from "next-intl";

/**
 * Lecture du thème depuis le DOM (classe `.dark` posée par le script inline
 * avant hydratation) via `useSyncExternalStore` : pas de `setState` dans un
 * effect, pas de mismatch d'hydratation, et synchronisation entre onglets.
 */
function subscribe(callback: () => void) {
  window.addEventListener("themechange", callback);
  window.addEventListener("storage", callback);
  return () => {
    window.removeEventListener("themechange", callback);
    window.removeEventListener("storage", callback);
  };
}

function getSnapshot() {
  return document.documentElement.classList.contains("dark");
}

function getServerSnapshot() {
  return false;
}

export function ThemeToggle() {
  const t = useTranslations("common");
  const isDark = useSyncExternalStore(subscribe, getSnapshot, getServerSnapshot);

  function toggle() {
    const next = !isDark;
    document.documentElement.classList.toggle("dark", next);
    try {
      localStorage.setItem("theme", next ? "dark" : "light");
    } catch {
      // stockage indisponible, on continue sans persister le choix
    }
    window.dispatchEvent(new Event("themechange"));
  }

  return (
    <button
      type="button"
      onClick={toggle}
      aria-label={t("toggleTheme")}
      aria-pressed={isDark}
      className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-[var(--border-soft)] bg-bg-card text-base transition-colors hover:border-brand-primary"
    >
      <span aria-hidden="true">{isDark ? "☀️" : "🌙"}</span>
    </button>
  );
}
