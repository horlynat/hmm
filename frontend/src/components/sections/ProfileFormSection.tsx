"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui";
import { ProfileForm } from "./ProfileForm";
import type { SessionUser } from "@/lib/types";

/**
 * Page de profil freelance : la vue par défaut est la présentation
 * (FreelanceProfileOverview), jamais le formulaire — celui-ci ne s'affiche
 * qu'à la demande explicite (tant qu'il reste des champs à compléter), et
 * disparaît complètement une fois le profil à 100 % (tout est verrouillé,
 * cf. ProfileForm::locked).
 */
export function ProfileFormSection({ user }: { user: SessionUser }) {
  const t = useTranslations("auth.profile");
  const [open, setOpen] = useState(false);

  if (user.profileCompletion >= 100) {
    return <p className="text-center text-sm text-(--color-muted)">{t("allFieldsLocked")}</p>;
  }

  if (!open) {
    return (
      <div className="flex justify-center">
        <Button onClick={() => setOpen(true)}>{t("completeCta")}</Button>
      </div>
    );
  }

  return <ProfileForm user={user} />;
}
