// Refonte, phase 1 : copie conforme du prototype (contenu figé du prototype).
// La phase 2 rebranche les données du back-office (cf. historique git de ce fichier).
import { notFound } from "next/navigation";
import ProtoRealisationsDbg from "@/components/protopages/realisations-dbg";
import ProtoRealisationsPortail from "@/components/protopages/realisations-portail";
import ProtoRealisationsAgent from "@/components/protopages/realisations-agent";

const PAGES = {
  dbg: ProtoRealisationsDbg,
  portail: ProtoRealisationsPortail,
  agent: ProtoRealisationsAgent,
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
