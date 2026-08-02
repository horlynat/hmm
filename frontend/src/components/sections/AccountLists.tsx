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
          className="group block rounded-(--radius-lg) focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-accent"
        >
          <Card
            variant="soft"
            className="h-full p-5 transition-all duration-200 group-hover:-translate-y-0.5 group-hover:border-brand-accent/30 group-hover:shadow-md"
          >
            <div className="mb-3 flex items-start justify-between gap-2">
              <span className="font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
                {project.title}
              </span>
              <Badge variant={projectStatusVariant(project.status)}>{project.statusLabel}</Badge>
            </div>
            <div className="mb-1.5 flex items-center justify-between text-xs text-(--color-muted)">
              <span>{labels.progress}</span>
              <span className="font-semibold text-brand-dark">{project.progress}%</span>
            </div>
            <div
              className="h-1.5 w-full overflow-hidden rounded-full bg-brand-light"
              role="progressbar"
              aria-valuenow={project.progress}
              aria-valuemin={0}
              aria-valuemax={100}
              aria-label={labels.progress}
            >
              <div
                className="h-full rounded-full bg-gradient-to-r from-brand-primary to-brand-accent transition-[width] duration-300"
                style={{ width: `${project.progress}%` }}
              />
            </div>
            <p className="mt-3 text-xs text-(--color-muted)">
              {labels.deadline}:{" "}
              <span className="font-medium text-brand-dark">
                {project.deadline ? new Date(project.deadline).toLocaleDateString() : labels.noDeadline}
              </span>
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
          className="group block rounded-(--radius-lg) focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-accent"
        >
          <Card
            variant="soft"
            className="h-full p-5 transition-all duration-200 group-hover:-translate-y-0.5 group-hover:border-brand-accent/30 group-hover:shadow-md"
          >
            <div className="mb-1.5 flex items-start justify-between gap-2">
              <span className="font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
                {quote.category}
              </span>
              <Badge variant={quoteStatusVariant(quote.status)}>
                {statusLabels[quote.status] ?? quote.status}
              </Badge>
            </div>
            {quote.budget && (
              <p className="text-sm text-(--color-muted)">
                {statusLabel}:{" "}
                <span className="font-medium text-brand-dark">
                  {quote.budget} {quote.currency ?? ""}
                </span>
              </p>
            )}
          </Card>
        </Link>
      ))}
    </div>
  );
}
