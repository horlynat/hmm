import { SimpleAuthPage } from "@/components/auth-proto/SimpleAuthPage";
import { SupportTicketFormPanel } from "@/components/support-proto/SupportTicketFormPanel";

export default function SupportTicketNewPage() {
  return (
    <SimpleAuthPage>
      <SupportTicketFormPanel />
    </SimpleAuthPage>
  );
}
