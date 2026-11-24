"use client";

import { useEffect, useRef, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { useParams } from "next/navigation";
import clsx from "clsx";
import { Link, usePathname, useRouter } from "@/i18n/navigation";
import { routing } from "@/i18n/routing";
import { WhatsappIcon } from "@/components/home/OfferIcon";
import { useAuthStatus } from "@/lib/auth/useAuthStatus";
import { whatsappLink } from "@/lib/whatsapp";
import { siteConfig } from "@/config/site";

/**
 * En-tête du site vitrine — markup et classes repris tels quels du
 * prototype de refonte (`.hd`, `.brand`, `.nav`, `.hr`, `.lsw`, `.pill`,
 * cf. src/app/proto-skin.css), pour un rendu identique. Données réelles :
 * navigation ([[config/site]].navItems, traduite), état de connexion
 * ([[lib/auth/useAuthStatus]]), langue courante (next-intl), lien WhatsApp
 * encodé ([[lib/whatsapp]]).
 */
/** Les 4 liens de l'en-tête du prototype, dans son ordre. */
const HEADER_NAV = [
  { key: "offres", href: "/offres" },
  { key: "apropos", href: "/a-propos" },
  { key: "blog", href: "/blog" },
  { key: "contact", href: "/contact" },
] as const;

function isCurrentSection(pathname: string, href: string): boolean {
  if (pathname === href) return true;
  if (href !== "/" && pathname.startsWith(href + "/")) return true;
  return href === "/a-propos" && pathname.startsWith("/realisations");
}

export function Header() {
  const t = useTranslations("nav");
  const tc = useTranslations("common");
  const tl = useTranslations("landing");
  const locale = useLocale();
  const router = useRouter();
  const pathname = usePathname();
  const params = useParams();
  const [open, setOpen] = useState(false);
  const navRef = useRef<HTMLElement>(null);
  const toggleButtonRef = useRef<HTMLButtonElement>(null);
  const firstLinkRef = useRef<HTMLAnchorElement>(null);
  const isAuthenticated = useAuthStatus();

  const accountHref = isAuthenticated ? "/compte" : "/connexion";
  const accountLabel = isAuthenticated ? t("compte") : t("espaceClient");

  // Ferme par la touche Échap et au clic en dehors du menu (mobile).
  useEffect(() => {
    if (!open) return;
    function onKey(e: KeyboardEvent) {
      if (e.key === "Escape") {
        setOpen(false);
        toggleButtonRef.current?.focus();
      }
    }
    function onClick(e: MouseEvent) {
      if (navRef.current && !navRef.current.contains(e.target as Node)) setOpen(false);
    }
    document.addEventListener("keydown", onKey);
    document.addEventListener("mousedown", onClick);
    return () => {
      document.removeEventListener("keydown", onKey);
      document.removeEventListener("mousedown", onClick);
    };
  }, [open]);

  useEffect(() => {
    if (open) firstLinkRef.current?.focus();
  }, [open]);

  function switchTo(nextLocale: (typeof routing.locales)[number]) {
    router.replace(
      // @ts-expect-error -- pathname vient d'une route connue de next-intl
      { pathname, params },
      { locale: nextLocale },
    );
  }

  return (
    <header className="hd">
      <div className="w">
        <Link href="/" className="brand" aria-label={tc("siteName")}>
          <span className="logo" aria-hidden="true">H</span>
          {tc("siteName")}
        </Link>

        <nav ref={navRef} className="nav" aria-label="Navigation principale">
          {HEADER_NAV.map((item, index) => (
            <Link
              key={item.href}
              href={item.href}
              ref={index === 0 ? firstLinkRef : undefined}
              onClick={() => setOpen(false)}
              aria-current={pathname === item.href ? "page" : undefined}
              // Comme header() du prototype : `on` = rubrique courante (sous-pages
              // comprises, une étude de cas allume « À propos ») ; `hi` = « Offres »
              // toujours mise en avant.
              className={clsx(isCurrentSection(pathname, item.href) && "on", item.href === "/offres" && "hi") || undefined}
            >
              {t(item.key)}
            </Link>
          ))}
        </nav>

        <div className="hr">
          {/* « Espace client » par défaut (aussi pendant la vérification de
              session) : même rendu que le prototype, sans décalage visuel. */}
          <Link href={accountHref} className="lnk2 l" title={tl("account.long")}>
            {accountLabel}
          </Link>

          <span className="lsw" role="group" aria-label={tc("langueLabel")}>
            {routing.locales.map((l) => (
              <button
                key={l}
                type="button"
                aria-pressed={l === locale}
                onClick={() => switchTo(l)}
              >
                {l.toUpperCase()}
              </button>
            ))}
          </span>

          <a
            className="b b-w sm"
            href={whatsappLink(tc("headerWhatsappMessage"))}
            target="_blank"
            rel="noopener noreferrer"
          >
            <WhatsappIcon />
            {tl("cta.whatsapp")}
          </a>

          <button
            ref={toggleButtonRef}
            type="button"
            className="burger"
            aria-label={open ? tc("closeMenu") : tc("menu")}
            aria-expanded={open}
            onClick={() => setOpen((v) => !v)}
          >
            <svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <path d={open ? "M6 6l12 12M18 6L6 18" : "M4 7h16M4 12h16M4 17h16"} />
            </svg>
          </button>
        </div>

        {open && (
          <div className="mm">
            {HEADER_NAV.map((item) => (
              <Link key={item.href} href={item.href} onClick={() => setOpen(false)}>
                {t(item.key)}
              </Link>
            ))}
            <a href={siteConfig.whatsappUrl} target="_blank" rel="noopener noreferrer" style={{ color: "#25D366" }}>
              {tl("cta.whatsapp")}
            </a>
            <Link href="/connexion" style={{ color: "var(--mu)" }} onClick={() => setOpen(false)}>
              {tl("account.long")}
            </Link>
          </div>
        )}
      </div>
    </header>
  );
}
