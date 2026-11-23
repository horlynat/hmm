import { notFound } from "next/navigation";
import { viewSupportTicket } from "@/actions/supportTicket";
import { supportTicketTokenSchema } from "@/lib/api/path";
import { SupportTicketThreadPanel } from "@/components/support-proto/SupportTicketThreadPanel";

export default async function SupportTicketThreadPage({ params }: { params: Promise<{ locale: string; token: string }> }) {
  const { token } = await params;
  // Jeton validé avant tout appel backend (64 hex) : un paramètre de route
  // arbitraire ne doit jamais être inséré dans un chemin d'API interne.
  if (!supportTicketTokenSchema.safeParse(token).success) notFound();
  const thread = await viewSupportTicket(token);
  if (!thread) notFound();

  return (
    <section className="authpg">
      <div className="w" style={{ maxWidth: "640px" }}>
        <SupportTicketThreadPanel token={token} thread={thread} />
      </div>
    </section>
  );
}
