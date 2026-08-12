import { ContactPageClient } from "./ContactPageClient";

// Requis pour la CSP stricte par nonce (cf. src/proxy.ts, src/lib/csp.ts) :
// un nonce ne peut être généré qu'au moment de la requête, donc cette page ne
// peut pas être prérendue statiquement.
export const dynamic = "force-dynamic";

export default async function ContactPage({
  searchParams,
}: {
  searchParams: Promise<{ mode?: string }>;
}) {
  // Lu côté serveur (plutôt que `useSearchParams()` côté client, qui exige
  // un `<Suspense>` dédié) : la page est déjà entièrement dynamique, `mode`
  // permet de pré-sélectionner un panneau (ex. "Proposer mon projet" depuis
  // /realisations doit ouvrir directement "Confier un projet", pas le mode
  // "Demander un devis" par défaut).
  const { mode } = await searchParams;
  return <ContactPageClient initialMode={mode} />;
}
