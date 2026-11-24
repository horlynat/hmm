import { SimpleAuthPage } from "@/components/auth-proto/SimpleAuthPage";
import { VerifyEmailStatusPanel } from "@/components/auth-proto/VerifyEmailStatusPanel";

export default async function VerifyEmailTokenPage({ params }: { params: Promise<{ locale: string; token: string }> }) {
  const { token } = await params;
  return (
    <SimpleAuthPage>
      <VerifyEmailStatusPanel token={token} />
    </SimpleAuthPage>
  );
}
