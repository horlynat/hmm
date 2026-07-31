import clsx from "clsx";

/** Bloc de chargement (placeholder) avec effet shimmer. Purement décoratif. */
export function Skeleton({ className }: { className?: string }) {
  return <div aria-hidden="true" className={clsx("skeleton", className)} />;
}
