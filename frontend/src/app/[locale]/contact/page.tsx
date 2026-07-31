import { ContactPageClient } from "./ContactPageClient";

// Requis pour la CSP stricte par nonce (cf. src/proxy.ts, src/lib/csp.ts) :
// un nonce ne peut être généré qu'au moment de la requête, donc cette page ne
// peut pas être prérendue statiquement.
export const dynamic = "force-dynamic";

export default function ContactPage() {
  return <ContactPageClient />;
}
