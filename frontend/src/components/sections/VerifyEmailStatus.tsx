"use client";

import { useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { Card } from "@/components/ui";
import { FormMessage } from "@/components/ui/form";
import { Link } from "@/i18n/navigation";
import { verifyEmailToken } from "@/lib/auth/actions";

/** Consomme le token de vérification dès le montage — aucune saisie requise côté client. */
export function VerifyEmailStatus({ token }: { token: string }) {
  const t = useTranslations("auth.verificationEmail");
  const [status, setStatus] = useState<"pending" | "success" | "error">("pending");
  const [serverError, setServerError] = useState("");

  useEffect(() => {
    let cancelled = false;
    verifyEmailToken(token).then((result) => {
      if (cancelled) return;
      if (result.ok) {
        setStatus("success");
      } else {
        setServerError(result.error);
        setStatus("error");
      }
    });
    return () => {
      cancelled = true;
    };
  }, [token]);

  if (status === "pending") {
    return (
      <Card variant="soft" className="p-8 text-center text-sm text-(--color-muted)">
        {t("verifying")}
      </Card>
    );
  }

  if (status === "success") {
    return (
      <Card variant="soft" className="p-8">
        <FormMessage variant="success">{t("confirmSuccess")}</FormMessage>
        <Link
          href="/connexion"
          className="mt-4 block text-center text-sm font-semibold text-brand-primary hover:underline"
        >
          {t("loginLink")}
        </Link>
      </Card>
    );
  }

  return (
    <Card variant="soft" className="p-8">
      <FormMessage variant="error">{serverError || t("confirmError")}</FormMessage>
      <Link
        href="/verification-email"
        className="mt-4 block text-center text-sm font-semibold text-brand-primary hover:underline"
      >
        {t("requestNewLink")}
      </Link>
    </Card>
  );
}
