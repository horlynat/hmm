import { routing } from "@/i18n/routing";
import { SiteNotFound } from "@/components/proto/SiteNotFound";

// `params` peut être absent pour ce fichier spécial (cf. [locale]/not-found.tsx) — repli sur la locale par défaut.
export default async function NotFound({ params }: { params?: Promise<{ locale?: string }> }) {
  const locale = (await params)?.locale ?? routing.defaultLocale;
  return <SiteNotFound locale={locale} />;
}
