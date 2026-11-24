import { getCurrentUser } from "@/lib/auth/session";
import { redirect } from "@/i18n/navigation";
import { SimpleAuthPage } from "@/components/auth-proto/SimpleAuthPage";
import { VerificationEmailPanel } from "@/components/auth-proto/VerificationEmailPanel";

export default async function VerificationEmailPage({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  const user = await getCurrentUser();
  if (user) redirect({ href: "/compte", locale });

  return (
    <SimpleAuthPage>
      <VerificationEmailPanel />
    </SimpleAuthPage>
  );
}
