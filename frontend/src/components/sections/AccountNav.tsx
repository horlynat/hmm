"use client";

import { useState, type ReactNode } from "react";
import { useTranslations } from "next-intl";
import clsx from "clsx";
import {
  LayoutDashboard,
  FolderKanban,
  FileText,
  Briefcase,
  Receipt,
  User,
  KeyRound,
  ShieldCheck,
  Settings,
  Download,
  Trash2,
  Plus,
  HelpCircle,
  MessagesSquare,
  ChevronDown,
  ChevronRight,
  PanelLeftClose,
  PanelLeftOpen,
  type LucideIcon,
} from "lucide-react";
import { Link, usePathname } from "@/i18n/navigation";
import { Badge, ButtonLink } from "@/components/ui";
import { LogoutButton } from "@/components/sections/LogoutButton";
import { getAvatarUrl } from "@/lib/media";

/**
 * Routes statiques (sans paramètre) de l'aside compte — cf. src/i18n/routing.ts.
 * "/aide" est la seule exception hors /compte : le centre d'aide public,
 * volontairement retiré du menu du site vitrine (cf. src/config/site.ts) et
 * accessible exclusivement depuis ici.
 */
type AccountPath =
  | "/compte"
  | "/compte/projets"
  | "/compte/devis"
  | "/compte/gestion-projet"
  | "/compte/factures"
  | "/compte/messages"
  | "/compte/profil"
  | "/compte/mot-de-passe"
  | "/compte/securite"
  | "/compte/parametres"
  | "/compte/export"
  | "/compte/supprimer"
  | "/aide";

interface NavItem {
  href: AccountPath;
  label: string;
  icon: LucideIcon;
}

/**
 * Lien de navigation de l'aside compte, avec indication visuelle + ARIA de la
 * page active. Pastille pleine arrondie (pas de barre latérale) : l'icône
 * change de couleur avec le libellé plutôt que de porter elle-même un fond,
 * pour rester lisible aussi bien seule (replié) qu'à côté d'un badge.
 */
function NavLink({
  href,
  label,
  icon: Icon,
  danger,
  badge,
  collapsed,
}: NavItem & { danger?: boolean; badge?: ReactNode; collapsed: boolean }) {
  const pathname = usePathname();
  const active = pathname === href;

  return (
    <Link
      href={href}
      aria-current={active ? "page" : undefined}
      aria-label={collapsed ? label : undefined}
      title={collapsed ? label : undefined}
      className={clsx(
        "flex items-center gap-2.5 rounded-full px-3 py-2 text-sm font-semibold transition-all",
        collapsed && "justify-center px-2",
        active
          ? danger
            ? "bg-danger/10 text-danger shadow-sm ring-1 ring-danger/10"
            : "bg-brand-primary/10 text-brand-primary shadow-sm ring-1 ring-brand-primary/15"
          : danger
            ? "text-danger/70 hover:bg-danger/10 hover:text-danger"
            : "text-(--color-muted) hover:bg-(--color-surface-muted) hover:text-(--brand-dark)",
      )}
    >
      <Icon aria-hidden="true" size={17} className="shrink-0" />
      {!collapsed && (
        <>
          <span className="flex-1 truncate">{label}</span>
          {badge}
        </>
      )}
    </Link>
  );
}

/**
 * Groupe de liens en accordéon (replié par défaut, sauf le groupe qui
 * contient la page active) — sans ça, les ~13 liens des deux groupes
 * s'empilent tous en permanence et poussent l'aside bien au-delà de la
 * hauteur de l'écran. `paths` sert à ouvrir automatiquement le groupe dès
 * qu'on y navigue (lien externe à l'aside, retour navigateur...), même s'il
 * avait été refermé manuellement — jamais l'inverse : quitter un groupe ne
 * le referme pas tout seul, pour ne pas surprendre un repli qu'on n'a pas
 * demandé. Repliée (rail 72px) : plus de place pour un intitulé cliquable,
 * les icônes du groupe restent donc affichées à plat, sans accordéon.
 */
function NavGroup({
  title,
  count,
  paths,
  collapsed,
  children,
}: {
  title: string;
  /** Nombre de liens dans le groupe, affiché en pastille discrète à côté du chevron — utile pour deviner ce que contient un groupe replié sans avoir à l'ouvrir. */
  count: number;
  paths: string[];
  collapsed: boolean;
  children: ReactNode;
}) {
  const pathname = usePathname();
  const containsActive = paths.includes(pathname);
  const [open, setOpen] = useState(containsActive);
  // Réouvre pendant le rendu plutôt que dans un effect (même pattern que
  // AccountShell pour previousPathname) : évite un rendu en cascade superflu.
  const [wasActive, setWasActive] = useState(containsActive);
  if (containsActive !== wasActive) {
    setWasActive(containsActive);
    if (containsActive) setOpen(true);
  }

  if (collapsed) {
    return (
      <div className="mt-3 border-t border-(--border-neutral)/60 pt-3 first:mt-0 first:border-0 first:pt-0">
        <div className="space-y-0.5">{children}</div>
      </div>
    );
  }

  return (
    <div className="mt-3 border-t border-(--border-neutral)/60 pt-3 first:mt-0 first:border-0 first:pt-0">
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        aria-expanded={open}
        className="flex w-full items-center justify-between gap-2 rounded-lg px-2 py-1 text-xs font-bold uppercase tracking-wider text-(--color-muted) transition-colors hover:text-(--brand-dark)"
      >
        <span className="flex items-center gap-1.5">
          {title}
          <span className="rounded-full bg-(--color-surface-muted) px-1.5 py-0.5 text-[10px] leading-none font-bold text-(--color-muted)">
            {count}
          </span>
        </span>
        <ChevronDown
          aria-hidden="true"
          size={14}
          className={clsx("shrink-0 transition-transform duration-200", open && "rotate-180")}
        />
      </button>
      {open && <div className="mt-1 space-y-0.5">{children}</div>}
    </div>
  );
}

interface NavUser {
  fullName: string | null;
  email: string;
  profileImage: string | null;
}

/**
 * Identité en tête d'aside — ancre visuelle vers /compte/profil, absente
 * jusqu'ici de la nav (seul l'avatar du header y renvoyait). Rangée plate
 * avec un simple filet en pied plutôt qu'une carte imbriquée dans la carte
 * que forme déjà l'aside : deux boîtes l'une dans l'autre alourdissaient le
 * bloc sans rien apporter. Replié : avatar seul, centré.
 */
function NavProfile({ user, collapsed }: { user: NavUser; collapsed: boolean }) {
  return (
    <Link
      href="/compte/profil"
      title={collapsed ? user.fullName ?? user.email : undefined}
      aria-label={collapsed ? (user.fullName ?? user.email) : undefined}
      className={clsx(
        "group flex items-center gap-2.5 rounded-lg p-1.5 transition-colors hover:bg-(--color-surface-muted)",
        collapsed && "justify-center",
      )}
    >
      {/* eslint-disable-next-line @next/next/no-img-element -- avatar externe (ui-avatars.com) ou média backend, hors domaines optimisables par next/image sans config supplémentaire */}
      <img
        src={getAvatarUrl(user)}
        alt=""
        className="h-10 w-10 shrink-0 rounded-full object-cover ring-2 ring-brand-primary/20 transition-colors group-hover:ring-brand-primary/40"
      />
      {!collapsed && (
        <>
          <span className="min-w-0 flex-1">
            <span className="block truncate text-sm font-bold text-(--brand-dark)">
              {user.fullName ?? user.email}
            </span>
            <span className="block truncate text-xs text-(--color-muted)">{user.email}</span>
          </span>
          <ChevronRight
            aria-hidden="true"
            size={15}
            className="shrink-0 text-(--color-muted) opacity-0 transition-opacity group-hover:opacity-100"
          />
        </>
      )}
    </Link>
  );
}

export interface AccountNavCounts {
  /** Demandes de devis au statut "pending" (cf. QuoteStatusEnum côté backend). */
  pendingQuotes: number;
  /** Total des projets affichés sur /compte/projets — non-collaborateurs uniquement, cf. NavLink ci-dessous. */
  myProjects: number;
  /** Total des projets confiés (collaborateurs uniquement) — affiché dans l'onglet "Mes projets" du hub, pas en badge. */
  managedProjects: number;
  /** Projets "à venir" pas encore affectés à une équipe — sert de badge au lien "Gestion de projet" (onglet "Projets disponibles" du hub). */
  availableProjects: number;
  /** Factures au statut "pending" (cf. InvoiceStatusEnum côté backend). */
  unpaidInvoices: number;
  /** Messages admin non lus dans la conversation candidat (cf. SessionUser.unreadMessagesCount). */
  unreadMessages: number;
}

interface AccountNavProps {
  user: NavUser;
  isCollaborator: boolean;
  counts: AccountNavCounts;
  collapsed?: boolean;
  onToggleCollapsed?: () => void;
}

/** Routes des deux groupes accordéon — sert à savoir lequel ouvrir automatiquement selon la page active, cf. NavGroup. */
const ACTIVITY_PATHS: AccountPath[] = [
  "/compte/projets",
  "/compte/devis",
  "/compte/gestion-projet",
  "/compte/factures",
  "/compte/messages",
];
const ACCOUNT_PATHS: AccountPath[] = [
  "/compte/profil",
  "/compte/mot-de-passe",
  "/compte/securite",
  "/compte/parametres",
  "/compte/export",
  "/aide",
];

export function AccountNav({
  user,
  isCollaborator,
  counts,
  collapsed = false,
  onToggleCollapsed,
}: AccountNavProps) {
  const t = useTranslations("auth.account.nav");
  // Toujours 4 liens dans ce groupe désormais : {Mes projets | Gestion de
  // projet} (l'un ou l'autre selon isCollaborator, jamais les deux) + devis +
  // factures + messages — la consolidation de "Gestion de projet" (qui
  // absorbe "Mes projets" et "Projets disponibles" pour un collaborateur) a
  // égalisé les deux cas, plus besoin de distinguer.
  const activityCount = 4;

  return (
    <nav aria-label={t("title")} className="flex h-full flex-col">
      {/* En-tête épinglé : identité + raccourci tableau de bord, jamais emporté par le défilement de la liste ci-dessous. */}
      <div className="shrink-0 space-y-1">
        <NavProfile user={user} collapsed={collapsed} />

        {!collapsed && (
          <p className="px-2 pb-1 text-xs font-bold uppercase tracking-wider text-(--color-muted)">{t("title")}</p>
        )}

        {!isCollaborator && (
          <div className={clsx("pb-1", collapsed && "flex justify-center")}>
            <ButtonLink
              href="/compte/devis/nouveau"
              variant="secondary"
              title={collapsed ? t("newQuoteCta") : undefined}
              aria-label={collapsed ? t("newQuoteCta") : undefined}
              className={clsx("text-xs", collapsed ? "!px-2.5 !py-2.5" : "w-full")}
            >
              <Plus aria-hidden="true" size={15} />
              {!collapsed && t("newQuoteCta")}
            </ButtonLink>
          </div>
        )}

        <NavLink href="/compte" label={t("dashboard")} icon={LayoutDashboard} collapsed={collapsed} />
      </div>

      {/* Zone scrollable : seule cette partie déborde si les deux groupes sont ouverts en même temps sur un petit écran — l'en-tête et le pied restent toujours atteignables sans défiler. */}
      <div className="min-h-0 flex-1 space-y-1 overflow-y-auto">
        <NavGroup title={t("groupActivity")} count={activityCount} paths={ACTIVITY_PATHS} collapsed={collapsed}>
        {!isCollaborator && (
          <NavLink
            href="/compte/projets"
            label={t("myProjects")}
            icon={FolderKanban}
            collapsed={collapsed}
            badge={counts.myProjects > 0 && <Badge variant="neutral">{counts.myProjects}</Badge>}
          />
        )}
        <NavLink
          href="/compte/devis"
          label={t("myQuotes")}
          icon={FileText}
          collapsed={collapsed}
          badge={
            counts.pendingQuotes > 0 && (
              <Badge variant="accent">{t("pendingQuotesBadge", { count: counts.pendingQuotes })}</Badge>
            )
          }
        />
        {isCollaborator && (
          <NavLink
            href="/compte/gestion-projet"
            label={t("projectManagement")}
            icon={Briefcase}
            collapsed={collapsed}
            badge={counts.availableProjects > 0 && <Badge variant="accent">{counts.availableProjects}</Badge>}
          />
        )}
        <NavLink
          href="/compte/factures"
          label={t("invoices")}
          icon={Receipt}
          collapsed={collapsed}
          badge={counts.unpaidInvoices > 0 && <Badge variant="warning">{counts.unpaidInvoices}</Badge>}
        />
        <NavLink
          href="/compte/messages"
          label={t("messages")}
          icon={MessagesSquare}
          collapsed={collapsed}
          badge={counts.unreadMessages > 0 && <Badge variant="accent">{counts.unreadMessages}</Badge>}
        />
      </NavGroup>

      <NavGroup title={t("groupAccount")} count={6} paths={ACCOUNT_PATHS} collapsed={collapsed}>
        <NavLink href="/compte/profil" label={t("profile")} icon={User} collapsed={collapsed} />
        <NavLink href="/compte/mot-de-passe" label={t("changePassword")} icon={KeyRound} collapsed={collapsed} />
        <NavLink href="/compte/securite" label={t("security")} icon={ShieldCheck} collapsed={collapsed} />
        <NavLink href="/compte/parametres" label={t("settings")} icon={Settings} collapsed={collapsed} />
        <NavLink href="/compte/export" label={t("dataExport")} icon={Download} collapsed={collapsed} />
        <NavLink href="/aide" label={t("help")} icon={HelpCircle} collapsed={collapsed} />
      </NavGroup>

      {/* Un seul lien : un accordéon n'apporterait rien ici, juste un clic de plus pour une action déjà rare. */}
      <div className="mt-3 border-t border-(--border-neutral)/60 pt-3">
        {!collapsed && (
          <p className="px-2 pb-1 text-xs font-bold uppercase tracking-wider text-(--color-muted)">
            {t("groupDanger")}
          </p>
        )}
        <NavLink href="/compte/supprimer" label={t("deleteAccount")} icon={Trash2} collapsed={collapsed} danger />
      </div>
      </div>

      {/* Pied épinglé : toujours atteignable sans avoir à défiler la liste. */}
      <div className="shrink-0">
        {!collapsed && (
          <div className="mt-3 border-t border-(--border-neutral)/60 pt-3">
            <LogoutButton />
          </div>
        )}

        {onToggleCollapsed && (
          <div className="mt-3 hidden border-t border-(--border-neutral)/60 pt-3 md:block">
            <button
              type="button"
              onClick={onToggleCollapsed}
              aria-label={collapsed ? t("expandMenu") : t("collapseMenu")}
              title={collapsed ? t("expandMenu") : t("collapseMenu")}
              className={clsx(
                "flex items-center gap-2 rounded-full px-3 py-2 text-xs font-semibold text-(--color-muted) transition-colors hover:bg-(--color-surface-muted) hover:text-(--brand-dark)",
                collapsed && "justify-center px-2",
              )}
            >
              {collapsed ? (
                <PanelLeftOpen aria-hidden="true" size={17} />
              ) : (
                <>
                  <PanelLeftClose aria-hidden="true" size={17} />
                  {t("collapseMenu")}
                </>
              )}
            </button>
          </div>
        )}
      </div>
    </nav>
  );
}
