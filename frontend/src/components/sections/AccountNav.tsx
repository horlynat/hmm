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
  PanelLeftClose,
  PanelLeftOpen,
  type LucideIcon,
} from "lucide-react";
import { Link, usePathname } from "@/i18n/navigation";
import { Badge, ButtonLink } from "@/components/ui";
import { LogoutButton } from "@/components/sections/LogoutButton";

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

/** Lien de navigation de l'aside compte, avec indication visuelle + ARIA de la page active. */
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
        "flex items-center gap-2.5 rounded-lg border-l-2 px-3 py-2 text-sm font-semibold transition-colors",
        collapsed && "justify-center px-2",
        active
          ? danger
            ? "border-danger bg-danger/10 text-danger"
            : "border-brand-primary bg-(--color-surface-muted) text-brand-primary"
          : danger
            ? "border-transparent text-danger/80 hover:bg-danger/10 hover:text-danger"
            : "border-transparent opacity-80 hover:bg-(--color-surface-muted) hover:opacity-100",
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

function NavGroup({ title, collapsed, children }: { title: string; collapsed: boolean; children: ReactNode }) {
  return (
    <div className="pt-3 first:pt-0">
      {!collapsed && (
        <p className="px-2 pb-1 text-xs font-bold uppercase tracking-wider opacity-40">{title}</p>
      )}
      <div className="space-y-0.5">{children}</div>
    </div>
  );
}

export interface AccountNavCounts {
  /** Demandes de devis au statut "pending" (cf. QuoteStatusEnum côté backend). */
  pendingQuotes: number;
  /** Total des projets affichés sur /compte/projets (client + éventuellement équipe). */
  myProjects: number;
  /** Total des projets affichés sur /compte/gestion-projet (collaborateurs uniquement). */
  managedProjects: number;
  /** Factures au statut "pending" (cf. InvoiceStatusEnum côté backend). */
  unpaidInvoices: number;
}

interface AccountNavProps {
  isCollaborator: boolean;
  counts: AccountNavCounts;
  collapsed?: boolean;
  onToggleCollapsed?: () => void;
}

export function AccountNav({
  isCollaborator,
  counts,
  collapsed = false,
  onToggleCollapsed,
}: AccountNavProps) {
  const t = useTranslations("auth.account.nav");

  return (
    <nav aria-label={t("title")} className="space-y-1">
      {!collapsed && (
        <p className="px-2 pb-2 text-xs font-bold uppercase tracking-wider opacity-40">{t("title")}</p>
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
        <NavLink
          href="/compte/factures"
          label={t("invoices")}
          icon={Receipt}
          collapsed={collapsed}
          badge={counts.unpaidInvoices > 0 && <Badge variant="warning">{counts.unpaidInvoices}</Badge>}
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
        <div className="pt-3">
          <LogoutButton />
        </div>
      )}

      {onToggleCollapsed && (
        <div className="hidden border-t border-(--border-neutral) pt-2 md:block">
          <button
            type="button"
            onClick={onToggleCollapsed}
            aria-label={collapsed ? t("expandMenu") : t("collapseMenu")}
            title={collapsed ? t("expandMenu") : t("collapseMenu")}
            className={clsx(
              "flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-semibold opacity-60 transition-opacity hover:opacity-100",
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
