// Refonte, phase 1 : copie conforme du prototype (contenu figé du prototype).
// La phase 2 rebranche les données du back-office (cf. historique git de ce fichier).
import { notFound } from "next/navigation";
import ProtoOffresWhatsapp from "@/components/protopages/offres-whatsapp";
import ProtoOffresSite from "@/components/protopages/offres-site";
import ProtoOffresAudit from "@/components/protopages/offres-audit";
import ProtoOffresAutomatisation from "@/components/protopages/offres-automatisation";
import ProtoOffresApplication from "@/components/protopages/offres-application";
import ProtoOffresArchitecture from "@/components/protopages/offres-architecture";
import ProtoOffresRisques from "@/components/protopages/offres-risques";

const PAGES = {
  whatsapp: ProtoOffresWhatsapp,
  site: ProtoOffresSite,
  audit: ProtoOffresAudit,
  automatisation: ProtoOffresAutomatisation,
  application: ProtoOffresApplication,
  architecture: ProtoOffresArchitecture,
  risques: ProtoOffresRisques,
} as const;

export const dynamicParams = false;

export function generateStaticParams() {
  return Object.keys(PAGES).map((slug) => ({ slug }));
}

export default async function Page({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  const Proto = Object.hasOwn(PAGES, slug) ? PAGES[slug as keyof typeof PAGES] : null;
  if (!Proto) notFound();
  return <Proto />;
}
