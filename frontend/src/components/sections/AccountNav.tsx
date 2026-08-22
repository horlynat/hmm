"use client";

import { type ReactNode } from "react";
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
        "flex items-center gap-2.5 rounded-[var(--radius-sm)] px-2.5 py-[7px] text-[12.8px] font-bold transition-colors",
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
      <Icon aria-hidden="true" size={16} className="shrink-0" />
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
 * Groupe de liens plat — plus d'accordéon repliable (cf. la maquette de
 * refonte validée : "Mon activité"/"Mon compte" y sont de simples labels
 * au-dessus de liens toujours visibles, sans bouton ni chevron). L'accordéon
 * avait été ajouté avant la maquette pour éviter que les ~16 liens d'alors
 * ne dépassent la hauteur de l'écran ; la consolidation de "Gestion de
 * projet" (cf. AccountNav ci-dessous) et la densité resserrée des lignes
 * ont ramené le total à ~11 liens, qui tiennent sans repli — l'accordéon
 * n'a donc plus de raison d'être et s'écartait du modèle validé.
 */
function NavGroup({ title, collapsed, children }: { title: string; collapsed: boolean; children: ReactNode }) {
  if (collapsed) {
    return (
      <div className="mt-3 border-t border-(--border-neutral)/60 pt-3 first:mt-0 first:border-0 first:pt-0">
        <div className="space-y-0.5">{children}</div>
      </div>
    );
  }

  return (
    <div className="mt-3 border-t border-(--border-neutral)/60 pt-3 first:mt-0 first:border-0 first:pt-0">
      <p className="px-2 pb-1 text-[10px] font-bold uppercase tracking-wider text-(--color-muted)">{title}</p>
      <div className="space-y-0.5">{children}</div>
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
        "group flex items-center gap-2.5 rounded-[var(--radius-sm)] p-[7px] transition-colors hover:bg-(--color-surface-muted)",
        collapsed && "justify-center",
      )}
    >
      {/* eslint-disable-next-line @next/next/no-img-element -- avatar externe (ui-avatars.com) ou média backend, hors domaines optimisables par next/image sans config supplémentaire */}
      <img
        src={getAvatarUrl(user)}
        alt=""
        className="h-8 w-8 shrink-0 rounded-full object-cover ring-2 ring-brand-primary/20 transition-colors group-hover:ring-brand-primary/40"
      />
      {!collapsed && (
        <>
          <span className="min-w-0 flex-1">
            <span className="block truncate text-[12.5px] font-bold text-(--brand-dark)">
              {user.fullName ?? user.email}
            </span>
            <span className="block truncate text-[10.5px] text-(--color-muted)">{user.email}</span>
          </span>
          <ChevronRight
            aria-hidden="true"
            size={14}
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

export function AccountNav({
  user,
  isCollaborator,
  counts,
  collapsed = false,
  onToggleCollapsed,
}: AccountNavProps) {
  const t = useTranslations("auth.account.nav");

  return (
    <nav aria-label={t("title")} className="flex flex-col">
      {/* En-tête : identité + raccourci tableau de bord. */}
      <div className="shrink-0 space-y-1">
        <NavProfile user={user} collapsed={collapsed} />

        {!collapsed && (
          <p className="px-2 pb-1 text-[10px] font-bold uppercase tracking-wider text-(--color-muted)">{t("title")}</p>
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

      {/* Plus de hauteur ni de défilement forcés ici : c'est l'aside (AccountShell)
          qui plafonne à la hauteur de l'écran et défile si besoin — comme la
          maquette, la nav épouse la hauteur de son contenu au lieu de toujours
          s'étirer sur 100% de l'écran (ce qui laissait un grand vide avant le
          pied de page une fois l'accordéon supprimé et les liens allégés). */}
      <div className="space-y-1">
        <NavGroup title={t("groupActivity")} collapsed={collapsed}>
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

      <NavGroup title={t("groupAccount")} collapsed={collapsed}>
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
          <p className="px-2 pb-1 text-[10px] font-bold uppercase tracking-wider text-(--color-muted)">
            {t("groupDanger")}
          </p>
        )}
        <NavLink href="/compte/supprimer" label={t("deleteAccount")} icon={Trash2} collapsed={collapsed} danger />
      </div>
      </div>

      {/* Pied : ancré en bas seulement s'il reste de la place (mt-auto) — plus de
          hauteur forcée pour "tirer" ce pied au fond de l'écran quel que soit le
          contenu au-dessus. */}
      <div className="mt-auto shrink-0">
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
