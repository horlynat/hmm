"use client";

import { useEffect, useRef, useState, useSyncExternalStore, type ReactNode } from "react";
import { useTranslations } from "next-intl";
import { ShieldAlert } from "lucide-react";
import { Link, usePathname } from "@/i18n/navigation";
import { AccountHeader } from "./AccountHeader";
import { AccountNav, type AccountNavCounts } from "./AccountNav";

const COLLAPSE_STORAGE_KEY = "account-nav-collapsed";
const COLLAPSE_EVENT = "account-nav-collapse-change";

/**
 * Même stratégie que ThemeToggle : lecture via `useSyncExternalStore` plutôt
 * qu'un `setState` dans un effect, pour éviter tout mismatch d'hydratation
 * (le serveur n'a pas accès à `localStorage`) sans render en cascade.
 */
function subscribeCollapsed(callback: () => void) {
  window.addEventListener(COLLAPSE_EVENT, callback);
  window.addEventListener("storage", callback);
  return () => {
    window.removeEventListener(COLLAPSE_EVENT, callback);
    window.removeEventListener("storage", callback);
  };
}

function getCollapsedSnapshot() {
  return localStorage.getItem(COLLAPSE_STORAGE_KEY) === "1";
}

function getCollapsedServerSnapshot() {
  return false;
}

interface AccountShellProps {
  user: { fullName: string | null; email: string; profileImage: string | null };
  isCollaborator: boolean;
  counts: AccountNavCounts;
  /**
   * Rappel non bloquant affiché en haut du contenu quand absent. Uniquement
   * pertinent pour les comptes exemptés de l'écran plein obligatoire
   * (ROLE_EDITOR, cf. CompteLayout) : les autres ne peuvent de toute façon
   * jamais atteindre AccountShell tant que ce n'est pas activé.
   */
  isTwoFactorEnabled: boolean;
  children: ReactNode;
}

/**
 * Structure de l'espace compte : header dédié (`AccountHeader`, distinct de
 * celui du site vitrine) + deux colonnes (aside gauche fixe / contenu droite)
 * à partir de `md:` ; en dessous, l'aside devient un tiroir (drawer) ouvert
 * depuis le bouton du header, plutôt que de s'empiler au-dessus du contenu —
 * sur un écran de téléphone, une vraie colonne de 260px pour 10 liens ne
 * laisse pas assez de place au contenu pour rester utilisable.
 */
export function AccountShell({ user, isCollaborator, counts, isTwoFactorEnabled, children }: AccountShellProps) {
  const t = useTranslations("auth.account.nav");
  const tc = useTranslations("common");
  const t2fa = useTranslations("auth.profile.security.twoFactorReminder");
  const pathname = usePathname();
  const [open, setOpen] = useState(false);
  const collapsed = useSyncExternalStore(subscribeCollapsed, getCollapsedSnapshot, getCollapsedServerSnapshot);
  const closeButtonRef = useRef<HTMLButtonElement>(null);

  function toggleCollapsed() {
    localStorage.setItem(COLLAPSE_STORAGE_KEY, collapsed ? "0" : "1");
    window.dispatchEvent(new Event(COLLAPSE_EVENT));
  }

  // Ferme le tiroir dès qu'on navigue (clic sur un lien du menu compte) — ajusté
  // pendant le rendu plutôt que dans un effect (cf. "Adjusting state when a prop
  // changes" de la doc React) pour éviter un rendu en cascade superflu.
  const [previousPathname, setPreviousPathname] = useState(pathname);
  if (pathname !== previousPathname) {
    setPreviousPathname(pathname);
    setOpen(false);
  }

  // À l'arrivée dans l'espace compte (typiquement juste après connexion), le
  // scroll hérite parfois de la position sur la page précédente au lieu de
  // repartir du haut (la transition client Next.js ne le réinitialise pas de
  // façon fiable ici).
  useEffect(() => {
    window.scrollTo(0, 0);
  }, []);

  // Échap pour fermer + focus rendu au bouton du header ; bloque le
  // défilement du fond pendant que le tiroir est ouvert (même logique que
  // le menu mobile du Header vitrine).
  useEffect(() => {
    if (!open) return;
    function onKey(e: KeyboardEvent) {
      if (e.key === "Escape") setOpen(false);
    }
    document.addEventListener("keydown", onKey);
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    return () => {
      document.removeEventListener("keydown", onKey);
      document.body.style.overflow = previousOverflow;
    };
  }, [open]);

  useEffect(() => {
    if (open) closeButtonRef.current?.focus();
  }, [open]);

  return (
    <>
      <AccountHeader user={user} menuOpen={open} onToggleMenu={() => setOpen((v) => !v)} />

      <div className="px-6 py-8">
        <div
          className={
            collapsed
              ? "mx-auto grid max-w-[1120px] gap-8 md:grid-cols-[72px_1fr] md:items-start"
              : "mx-auto grid max-w-[1120px] gap-8 md:grid-cols-[260px_1fr] md:items-start"
          }
        >
          <aside className="sticky top-20 hidden border-r border-(--border-neutral) pr-4 md:block">
            <AccountNav
              isCollaborator={isCollaborator}
              counts={counts}
              collapsed={collapsed}
              onToggleCollapsed={toggleCollapsed}
            />
          </aside>

          <div className="min-w-0">
            {!isTwoFactorEnabled && (
              <Link
                href="/compte/securite"
                className="mb-5 flex items-center gap-3 rounded-[var(--radius-md)] border border-(--color-badge-warning-text)/25 bg-warning/10 px-4 py-3 text-sm text-(--color-badge-warning-text) transition hover:bg-warning/15"
              >
                <ShieldAlert size={18} className="shrink-0" aria-hidden="true" />
                <span className="min-w-0 flex-1">{t2fa("message")}</span>
                <span className="shrink-0 font-semibold whitespace-nowrap underline decoration-dotted underline-offset-2">
                  {t2fa("cta")}
                </span>
              </Link>
            )}
            {children}
          </div>
        </div>
      </div>

      {open && (
        <div className="fixed inset-0 z-[60] md:hidden">
          <div
            className="absolute inset-0 bg-black/40"
            onClick={() => setOpen(false)}
            aria-hidden="true"
          />
          <div
            id="account-drawer"
            role="dialog"
            aria-modal="true"
            aria-label={t("title")}
            className="absolute inset-y-0 left-0 w-[85%] max-w-[320px] overflow-y-auto bg-bg-default p-4 shadow-xl"
          >
            <div className="mb-2 flex items-center justify-end">
              <button
                ref={closeButtonRef}
                type="button"
                aria-label={tc("closeMenu")}
                onClick={() => setOpen(false)}
                className="flex h-9 w-9 items-center justify-center rounded-full border border-(--border-neutral) bg-bg-card"
              >
                <span aria-hidden="true">✕</span>
              </button>
            </div>
            <AccountNav isCollaborator={isCollaborator} counts={counts} />
          </div>
        </div>
      )}
    </>
  );
}
