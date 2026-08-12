import { notFound } from "next/navigation";
import { SupportTicketThread } from "@/components/sections/SupportTicketThread";
import { viewSupportTicket } from "@/actions/supportTicket";

export default async function SupportTicketThreadPage({
  params,
}: {
  params: Promise<{ locale: string; token: string }>;
}) {
  const { token } = await params;
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
