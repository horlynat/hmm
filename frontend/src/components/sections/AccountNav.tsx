"use client";

import type { ReactNode } from "react";
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
  Rocket,
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
  | "/compte/projets-disponibles"
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
        "flex items-center gap-2.5 rounded-full px-3 py-2 text-sm font-semibold transition-colors",
        collapsed && "justify-center px-2",
        active
          ? danger
            ? "bg-danger/10 text-danger"
            : "bg-brand-primary/10 text-brand-primary"
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

/** Groupe de liens, séparé du précédent par un filet fin plutôt qu'un simple espacement — rythme plus net entre les sections. */
function NavGroup({ title, collapsed, children }: { title: string; collapsed: boolean; children: ReactNode }) {
  return (
    <div className="mt-3 border-t border-(--border-neutral)/60 pt-3 first:mt-0 first:border-0 first:pt-0">
      {!collapsed && (
        <p className="px-2 pb-1 text-xs font-bold uppercase tracking-wider text-(--color-muted)">{title}</p>
      )}
      <div className="space-y-0.5">{children}</div>
    </div>
  );
}

interface NavUser {
  fullName: string | null;
  email: string;
  profileImage: string | null;
}

/** Carte d'identité en tête d'aside — ancre visuelle vers /compte/profil, absente jusqu'ici de la nav (seul l'avatar du header y renvoyait). Replié : avatar seul, centré. */
function NavProfile({ user, collapsed }: { user: NavUser; collapsed: boolean }) {
  return (
    <Link
      href="/compte/profil"
      title={collapsed ? user.fullName ?? user.email : undefined}
      aria-label={collapsed ? (user.fullName ?? user.email) : undefined}
      className={clsx(
        "mb-3 flex items-center gap-2.5 rounded-xl border border-(--border-neutral) bg-(--color-surface-muted) p-2 transition-colors hover:border-brand-primary/40",
        collapsed && "justify-center",
      )}
    >
      {/* eslint-disable-next-line @next/next/no-img-element -- avatar externe (ui-avatars.com) ou média backend, hors domaines optimisables par next/image sans config supplémentaire */}
      <img src={getAvatarUrl(user)} alt="" className="h-8 w-8 shrink-0 rounded-full object-cover" />
      {!collapsed && (
        <span className="min-w-0 flex-1">
          <span className="block truncate text-sm font-bold text-(--brand-dark)">
            {user.fullName ?? user.email}
          </span>
          <span className="block truncate text-xs text-(--color-muted)">{user.email}</span>
        </span>
      )}
    </Link>
  );
}

export interface AccountNavCounts {
  /** Demandes de devis au statut "pending" (cf. QuoteStatusEnum côté backend). */
  pendingQuotes: number;
  /** Total des projets affichés sur /compte/projets (client + éventuellement équipe). */
  myProjects: number;
  /** Total des projets affichés sur /compte/gestion-projet (collaborateurs uniquement). */
  managedProjects: number;
  /** Projets "à venir" pas encore affectés à une équipe, sur /compte/projets-disponibles. */
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

export function AccountNav({
  user,
  isCollaborator,
  counts,
  collapsed = false,
  onToggleCollapsed,
}: AccountNavProps) {
  const t = useTranslations("auth.account.nav");

  return (
    <nav aria-label={t("title")} className="space-y-1">
      <NavProfile user={user} collapsed={collapsed} />

      {!collapsed && (
        <p className="px-2 pb-2 text-xs font-bold uppercase tracking-wider text-(--color-muted)">{t("title")}</p>
      )}

      {!isCollaborator && (
        <div className={clsx("pb-2", collapsed && "flex justify-center")}>
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

      <NavGroup title={t("groupActivity")} collapsed={collapsed}>
        <NavLink
          href="/compte/projets"
          label={t("myProjects")}
          icon={FolderKanban}
          collapsed={collapsed}
          badge={counts.myProjects > 0 && <Badge variant="neutral">{counts.myProjects}</Badge>}
        />
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
            badge={counts.managedProjects > 0 && <Badge variant="neutral">{counts.managedProjects}</Badge>}
          />
        )}
        {isCollaborator && (
          <NavLink
            href="/compte/projets-disponibles"
            label={t("availableProjects")}
            icon={Rocket}
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

      <NavGroup title={t("groupAccount")} collapsed={collapsed}>
        <NavLink href="/compte/profil" label={t("profile")} icon={User} collapsed={collapsed} />
        <NavLink href="/compte/mot-de-passe" label={t("changePassword")} icon={KeyRound} collapsed={collapsed} />
        <NavLink href="/compte/securite" label={t("security")} icon={ShieldCheck} collapsed={collapsed} />
        <NavLink href="/compte/parametres" label={t("settings")} icon={Settings} collapsed={collapsed} />
        <NavLink href="/compte/export" label={t("dataExport")} icon={Download} collapsed={collapsed} />
        <NavLink href="/aide" label={t("help")} icon={HelpCircle} collapsed={collapsed} />
      </NavGroup>

      <NavGroup title={t("groupDanger")} collapsed={collapsed}>
        <NavLink href="/compte/supprimer" label={t("deleteAccount")} icon={Trash2} collapsed={collapsed} danger />
      </NavGroup>

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
    </nav>
  );
}
