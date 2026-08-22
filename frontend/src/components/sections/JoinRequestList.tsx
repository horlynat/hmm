import { getTranslations } from "next-intl/server";
import { Clock, CheckCircle2, XCircle } from "lucide-react";
import { Badge, Card, EmptyState } from "@/components/ui";
import { Link } from "@/i18n/navigation";
import type { SessionJoinRequest } from "@/lib/types";
import { joinRequestStatusVariant } from "@/lib/status";

const STATUS_ICON = {
  pending: Clock,
  approved: CheckCircle2,
  rejected: XCircle,
} as const;

/**
 * Historique des demandes d'auto-association du freelance courant — onglet
 * "Mes demandes" du hub /compte/gestion-projet (GET /api/me/projects/join-requests).
 * Une demande approuvée pointe vers l'espace de travail du projet, désormais
 * accessible ; les autres statuts n'ont rien à ouvrir.
 */
export async function JoinRequestList({ requests, locale }: { requests: SessionJoinRequest[]; locale: string }) {
  const t = await getTranslations({ locale, namespace: "auth.projectManagement.requests" });

  if (requests.length === 0) {
    return <EmptyState icon={Clock} message={t("empty")} />;
  }

  return (
    <Card variant="soft" className="p-4">
      <ol className="space-y-4">
        {requests.map((request) => {
          const Icon = STATUS_ICON[request.status];
          const date = new Date(request.requestedAt).toLocaleDateString(locale, {
            day: "numeric",
            month: "long",
            year: "numeric",
          });
          const content = (
            <>
              <span className="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-(--color-surface-muted) text-brand-primary">
                <Icon size={14} aria-hidden="true" />
              </span>
              <div className="min-w-0 flex-1">
                <p className="text-sm font-semibold">{request.project.title}</p>
                <p className="mt-0.5 text-xs text-(--color-muted)">{t("requestedOn", { date })}</p>
              </div>
              <Badge variant={joinRequestStatusVariant(request.status)}>{t(`status.${request.status}`)}</Badge>
            </>
          );

          return (
            <li key={request.id} className="flex items-center gap-3">
              {request.status === "approved" ? (
                <Link
                  href={{ pathname: "/compte/gestion-projet/[id]", params: { id: String(request.project.id) } }}
                  className="flex min-w-0 flex-1 items-center gap-3 rounded-lg px-2 py-1 -my-1 transition-colors hover:bg-(--color-surface-muted)"
                >
                  {content}
                </Link>
              ) : (
                <div className="flex min-w-0 flex-1 items-center gap-3 px-2 py-1 -my-1">{content}</div>
              )}
            </li>
          );
        })}
      </ol>
    </Card>
  );
}
