import { getTranslations } from "next-intl/server";
import { MessagesSquare } from "lucide-react";
import { PageHeader } from "@/components/ui";
import { CandidateMessageThread } from "@/components/sections/CandidateMessageThread";
import { getCurrentUser } from "@/lib/auth/session";
import { getCandidateMessages } from "@/lib/auth/actions";

export default async function CompteMessagesPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const user = await getCurrentUser();
  if (!user) return null;

  const t = await getTranslations({ locale, namespace: "auth.account.messagesPage" });
  const messages = (await getCandidateMessages()) ?? [];

  return (
    <div className="max-w-3xl space-y-6">
      <PageHeader icon={MessagesSquare} title={t("title")} subtitle={t("subtitle")} />
      <CandidateMessageThread messages={messages} />
    </div>
  );
}
