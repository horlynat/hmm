import type { ProjectStatus } from "@/lib/types";

type BadgeVariant = "neutral" | "success" | "warning" | "danger" | "info" | "accent";

/** Couleur de badge par statut de projet — cf. ProjectStatus (src/lib/types/index.ts). */
const PROJECT_STATUS_VARIANT: Record<ProjectStatus, BadgeVariant> = {
  a_venir: "info",
  en_cours: "success",
  collaboration: "accent",
  suspendu: "warning",
  termine: "neutral",
};

/**
 * Prend un `string` (pas `ProjectStatus`) : `SessionProject.status` — la
 * variante allégée renvoyée par GET /api/me — est typée `string` côté
 * frontend, contrairement à `SessionProjectDetail.status`.
 */
export function projectStatusVariant(status: string): BadgeVariant {
  return PROJECT_STATUS_VARIANT[status as ProjectStatus] ?? "neutral";
}

/**
 * Couleur de badge par statut de devis — miroir de QuoteStatusEnum côté
 * backend (App\Enum\QuoteStatusEnum). "suspended" en neutre plutôt qu'en
 * warning/danger : c'est un état non-terminal ("suspendu temporairement, puis
 * repris" selon le commentaire de l'enum), à distinguer visuellement de
 * "pending" (attend une action) et "rejected" (état terminal négatif).
 */
const QUOTE_STATUS_VARIANT: Record<string, BadgeVariant> = {
  pending: "warning",
  accepted: "success",
  suspended: "neutral",
  rejected: "danger",
};

export function quoteStatusVariant(status: string): BadgeVariant {
  return QUOTE_STATUS_VARIANT[status] ?? "neutral";
}

/** Couleur de badge par statut de facture — miroir de InvoiceStatusEnum côté backend. */
const INVOICE_STATUS_VARIANT: Record<string, BadgeVariant> = {
  pending: "warning",
  revision_requested: "info",
  paid: "success",
  cancelled: "neutral",
};

export function invoiceStatusVariant(status: string): BadgeVariant {
  return INVOICE_STATUS_VARIANT[status] ?? "neutral";
}

/** Couleur de badge par statut de demande d'auto-association — miroir de ProjectJoinRequestStatusEnum côté backend. */
const JOIN_REQUEST_STATUS_VARIANT: Record<string, BadgeVariant> = {
  pending: "warning",
  approved: "success",
  rejected: "danger",
};

export function joinRequestStatusVariant(status: string): BadgeVariant {
  return JOIN_REQUEST_STATUS_VARIANT[status] ?? "neutral";
}
