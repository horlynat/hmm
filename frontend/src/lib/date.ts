/** Formate une date ISO en "MM/YYYY" — format demandé pour les expériences et formations. */
export function formatMonthYear(iso: string): string {
  const date = new Date(iso);
  const month = String(date.getMonth() + 1).padStart(2, "0");
  return `${month}/${date.getFullYear()}`;
}

/**
 * Plage "MM/YYYY – MM/YYYY", ou "MM/YYYY – {presentLabel}" si `end` est
 * absent (poste actuel — cf. Experience::$endDate nullable côté backend).
 */
export function formatDateRange(start: string, end: string | null, presentLabel: string): string {
  const startLabel = formatMonthYear(start);
  return end ? `${startLabel} – ${formatMonthYear(end)}` : `${startLabel} – ${presentLabel}`;
}
