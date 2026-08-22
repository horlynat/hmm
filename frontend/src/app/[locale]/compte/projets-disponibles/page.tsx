import { redirect } from "@/i18n/navigation";

/**
 * Route absorbée par l'onglet "Projets disponibles" du hub
 * /compte/gestion-projet (cf. la maquette de refonte validée — plus de lien
 * d'aside dédié). Conservée comme redirection pour ne pas casser un favori
 * ou un lien externe existant.
 */
export default async function ProjetsDisponiblesPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  redirect({ href: { pathname: "/compte/gestion-projet", query: { tab: "open" } }, locale });
}
