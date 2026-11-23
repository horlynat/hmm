"use client";

import { useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { verifyEmailToken } from "@/lib/auth/actions";
import { ProtoIcon } from "@/components/proto/ProtoSvg";

/** Consomme le token de vérification au montage — logique identique à `VerifyEmailStatus`. */
export function VerifyEmailStatusPanel({ token }: { token: string }) {
  const t = useTranslations("auth.verificationEmail");
  const [status, setStatus] = useState<"pending" | "success" | "error">("pending");
  const [serverError, setServerError] = useState("");

  useEffect(() => {
    let cancelled = false;
    verifyEmailToken(token).then((result) => {
      if (cancelled) return;
      if (result.ok) setStatus("success");
      else {
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
      <div className="auth" role="status">
        <h2 tabIndex={-1}>{t("confirmTitle")}</h2>
        <p className="mut">{t("verifying")}</p>
      </div>
    );
  }

  if (status === "success") {
    return (
      <div className="auth" role="status">
        <span className="okb">
          <ProtoIcon name="ok" size={22} />
        </span>
        <h2 tabIndex={-1}>{t("confirmTitle")}</h2>
        <p className="mut">{t("confirmSuccess")}</p>
        <Link className="b b-g" href="/connexion">
          {t("loginLink")}
        </Link>
      </div>
    );
  }

  return (
    <div className="auth" role="alert">
      <h2 tabIndex={-1}>{t("confirmTitle")}</h2>
      <p className="qerr" style={{ marginTop: 0 }}>
        {serverError || t("confirmError")}
      </p>
      <Link className="b b-g" href="/verification-email">
        {t("requestNewLink")}
      </Link>
    </div>
  );
}
