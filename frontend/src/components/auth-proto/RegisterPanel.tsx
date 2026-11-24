"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { ProtoIcon } from "@/components/proto/ProtoSvg";
import { ClientSignupForm } from "./ClientSignupForm";
import { FreelanceSignupForm } from "./FreelanceSignupForm";

type Role = "client" | "free";

/** Panneau « Créer un compte » : sélection du profil (prototype `.roles .tile`), puis le formulaire correspondant. */
export function RegisterPanel({ onRoleChange }: { onRoleChange: (role: Role | null) => void }) {
  const t = useTranslations("authPage");
  const [role, setRole] = useState<Role | null>(null);

  function selectRole(next: Role) {
    setRole(next);
    onRoleChange(next);
  }

  function changeRole() {
    setRole(null);
    onRoleChange(null);
  }

  if (role === "client") return <ClientSignupForm onChangeRole={changeRole} />;
  if (role === "free") return <FreelanceSignupForm onChangeRole={changeRole} />;

  return (
    <div className="auth">
      <h2 tabIndex={-1}>{t("chooseRole")}</h2>
      <p className="mut">{t("chooseRoleLede")}</p>
      <div className="roles">
        <button type="button" className="tile" onClick={() => selectRole("client")}>
          <span className="oic">
            <ProtoIcon name="app" />
          </span>
          <span>
            <b>{t("roleClient")}</b>
            <small>{t("roleClientDesc")}</small>
          </span>
        </button>
        <button type="button" className="tile" onClick={() => selectRole("free")}>
          <span className="oic">
            <svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <circle cx="9" cy="7" r="4" />
              <path d="M3 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2" />
              <path d="M16 3.13a4 4 0 0 1 0 7.75M21 21v-2a4 4 0 0 0-3-3.85" />
            </svg>
          </span>
          <span>
            <b>{t("roleFreelance")}</b>
            <small>{t("roleFreelanceDesc")}</small>
          </span>
        </button>
      </div>
    </div>
  );
}
