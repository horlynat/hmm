"use client";

import { useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { FormMessage } from "@/components/ui/form";
import { resendVerificationEmail } from "@/lib/auth/actions";

export function ResendVerificationButton() {
  const t = useTranslations("auth.profile.security");
  const [pending, startTransition] = useTransition();
  const [status, setStatus] = useState<"idle" | "success" | "error">("idle");

  function handleClick() {
    setStatus("idle");
    startTransition(async () => {
      const result = await resendVerificationEmail();
      setStatus(result.ok ? "success" : "error");
    });
  }

  return (
    <div>
      <button type="button" className="btn-secondary btn-sm" disabled={pending} onClick={handleClick}>
        {pending ? t("resendPending") : t("resendButton")}
      </button>
      {status === "success" && (
        <FormMessage variant="success" className="mt-2">
          {t("resendSuccess")}
        </FormMessage>
      )}
      {status === "error" && (
        <FormMessage variant="error" className="mt-2">
          {t("resendError")}
        </FormMessage>
      )}
    </div>
  );
}
