import { notFound } from "next/navigation";
import { SupportTicketThread } from "@/components/sections/SupportTicketThread";
import { viewSupportTicket } from "@/actions/supportTicket";
import { supportTicketTokenSchema } from "@/lib/api/path";

export default async function SupportTicketThreadPage({
  params,
}: {
  params: Promise<{ locale: string; token: string }>;
}) {
  const { token } = await params;
  // Jeton validé avant tout appel backend (64 hex) : un paramètre de route
  // arbitraire ne doit jamais être inséré dans un chemin d'API interne.
  if (!supportTicketTokenSchema.safeParse(token).success) {
    notFound();
  }
  const thread = await viewSupportTicket(token);

  if (!thread) {
    notFound();
  }

  return (
    <section className="px-6 py-16">
      <div className="mx-auto max-w-[640px]">
        <SupportTicketThread token={token} thread={thread} />
      </div>
    </section>
  );
}
