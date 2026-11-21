"use client";

import { useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui";
import { FormMessage, TextInput } from "@/components/ui/form";
import { useRouter } from "@/i18n/navigation";
import { deleteAccount } from "@/lib/auth/actions";

export function DeleteAccountSection() {
  const t = useTranslations("auth.profile.dangerZone");
  const router = useRouter();
  const [confirming, setConfirming] = useState(false);
  const [pending, startTransition] = useTransition();
  const [error, setError] = useState("");
  const [currentPassword, setCurrentPassword] = useState("");

  function handleDelete() {
    setError("");
    if (!currentPassword) {
      setError(t("passwordRequired"));
      return;
    }
    startTransition(async () => {
      const result = await deleteAccount(currentPassword);
      if (result.ok) {
        router.push("/");
        router.refresh();
      } else {
        setError(result.error === "invalid_input" ? t("passwordRequired") : result.error);
      }
    });
  }

  return (
    <div>
      {!confirming ? (
        <Button variant="secondary" onClick={() => setConfirming(true)}>
          {t("deleteButton")}
        </Button>
      ) : (
        <div className="space-y-2">
          <p className="text-xs font-semibold text-danger">{t("confirmPrompt")}</p>
          <TextInput
            label={t("passwordLabel")}
            type="password"
            autoComplete="current-password"
            value={currentPassword}
            onChange={(e) => setCurrentPassword(e.target.value)}
            disabled={pending}
          />
          <div className="flex flex-wrap items-center gap-2">
            <Button
              variant="secondary"
              className="border-danger text-danger hover:bg-danger/10"
              disabled={pending}
              onClick={handleDelete}
            >
              {pending ? t("confirmPending") : t("confirmButton")}
            </Button>
            <Button
              variant="secondary"
              disabled={pending}
              onClick={() => {
                setConfirming(false);
                setCurrentPassword("");
                setError("");
              }}
            >
              {t("cancelButton")}
            </Button>
          </div>
        </div>
      )}

      {error && (
        <FormMessage variant="error" className="mt-2">
          {error}
        </FormMessage>
      )}
    </div>
  );
}
