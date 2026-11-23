import { SimpleAuthPage } from "@/components/auth-proto/SimpleAuthPage";
import { ResetPasswordPanel } from "@/components/auth-proto/ResetPasswordPanel";

export default async function ResetPasswordPage({ params }: { params: Promise<{ locale: string; token: string }> }) {
  const { token } = await params;
  return (
    <SimpleAuthPage>
      <ResetPasswordPanel token={token} />
    </SimpleAuthPage>
  );
}
