import { SiteHome } from "@/components/landing/SiteHome";

// Rendu statique, rafraîchi par les tags de l'API (webhook /api/revalidate).
export const dynamic = "force-static";

export default async function HomePage({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  return <SiteHome locale={locale} />;
}
