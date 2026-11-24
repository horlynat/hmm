import { SiteAide } from "@/components/support-proto/SiteAide";

export const dynamic = "force-static";

export default async function HelpCenterPage({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  return <SiteAide locale={locale} />;
}
