import { Badge, Card } from "@/components/ui";
import { Link } from "@/i18n/navigation";
import type { SessionProject, SessionQuote } from "@/lib/types";
import { projectStatusVariant, quoteStatusVariant } from "@/lib/status";

/** Cartes de projets réutilisées entre le tableau de bord (aperçu) et /compte/projets (liste complète). */
export function ProjectList({
  projects,
  labels,
}: {
  projects: SessionProject[];
  labels: { progress: string; deadline: string; noDeadline: string };
}) {
  return (
    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
      {projects.map((project) => (
        <Link
          key={project.id}
          href={{ pathname: "/compte/projets/[id]", params: { id: String(project.id) } }}
          className="rounded-(--radius-md) focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary"
        >
          <Card variant="soft" className="p-4 transition hover:shadow-md">
            <div className="mb-2 flex items-start justify-between gap-2">
              <span className="font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
                {project.title}
              </span>
              <Badge variant={projectStatusVariant(project.status)}>{project.statusLabel}</Badge>
            </div>
            <div className="mb-1 flex items-center justify-between text-xs opacity-60">
              <span>{labels.progress}</span>
              <span>{project.progress}%</span>
            </div>
            <div
              className="h-1.5 w-full rounded-full bg-brand-light"
              role="progressbar"
              aria-valuenow={project.progress}
              aria-valuemin={0}
              aria-valuemax={100}
              aria-label={labels.progress}
            >
              <div
                className="h-1.5 rounded-full bg-brand-primary"
                style={{ width: `${project.progress}%` }}
              />
            </div>
            <p className="mt-2 text-xs opacity-60">
              {labels.deadline}:{" "}
              {project.deadline
                ? new Date(project.deadline).toLocaleDateString()
                : labels.noDeadline}
            </p>
          </Card>
        </Link>
      ))}
    </div>
  );
}

export function QuoteList({
  quotes,
  statusLabel,
  statusLabels,
}: {
  quotes: SessionQuote[];
  statusLabel: string;
  /** Libellés traduits des statuts de devis (cf. auth.account.quoteStatus) — `SessionQuote.status` n'est pas pré-traduit côté backend, contrairement à `SessionQuoteDetail.statusLabel`. */
  statusLabels: Record<string, string>;
}) {
  return (
    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
      {quotes.map((quote) => (
        <Link
          key={quote.id}
          href={{ pathname: "/compte/devis/[id]", params: { id: String(quote.id) } }}
          className="rounded-(--radius-md) focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary"
        >
          <Card variant="soft" className="p-4 transition hover:shadow-md">
            <div className="mb-1 flex items-start justify-between gap-2">
              <span className="font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
                {quote.category}
              </span>
              <Badge variant={quoteStatusVariant(quote.status)}>
                {statusLabels[quote.status] ?? quote.status}
              </Badge>
            </div>
            {quote.budget && (
              <p className="text-sm opacity-70">
                {statusLabel}: {quote.budget} {quote.currency ?? ""}
              </p>
            )}
          </Card>
        </Link>
      ))}
    </div>
  );
}
