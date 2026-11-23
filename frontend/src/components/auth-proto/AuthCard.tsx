"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { whatsappLink } from "@/lib/whatsapp";
import { siteConfig } from "@/config/site";
import { LoginPanel } from "./LoginPanel";
import { ForgotPanel } from "./ForgotPanel";
import { RegisterPanel } from "./RegisterPanel";

type Tab = "in" | "up";

/**
 * Carte d'authentification complète (prototype `pLogin()`/`authBox()` :
 * `.ag .aside2 .acard .atabs`) : connexion (avec double authentification et
 * mot de passe oublié) et création de compte (client ou freelance), chacun
 * branché sur la logique réelle déjà testée (LoginForm, ForgotPasswordForm,
 * ClientForm, FreelanceForm).
 */
export function AuthCard({ initialTab, initialForgot }: { initialTab: Tab; initialForgot?: boolean }) {
  const t = useTranslations("authPage");
  const [tab, setTab] = useState<Tab>(initialTab);
  const [forgot, setForgot] = useState(Boolean(initialForgot));
  const [role, setRole] = useState<"client" | "free" | null>(null);

  function selectTab(next: Tab) {
    setTab(next);
    setForgot(false);
    setRole(null);
  }

  const showFreelanceSide = tab === "up" && role === "free";
  const points = showFreelanceSide ? (t.raw("freelancePoints") as [string, string][]) : (t.raw("clientPoints") as [string, string][]);

  return (
    <div className="w ag">
      <div className="aside2">
        <span className="alogo">
          <span className="logo">H</span>
          {siteConfig.name.split(" ")[0]}
        </span>
        <h1>
          {t("title")}
          <span className="grad">{t("titleAccent")}</span>
        </h1>
        <ul className="chk">
          {points.map(([title, text]) => (
            <li key={title}>
              <i>✓</i>
              <span>
                <b>{title}</b>
                <br />
                <span className="mut">{text}</span>
              </span>
            </li>
          ))}
        </ul>
        <p className="dm" style={{ fontSize: "14px", marginTop: "28px" }}>
          {t("helpText")}{" "}
          <a className="lnk" href={whatsappLink(t("helpMessage"))} target="_blank" rel="noopener noreferrer">
            {t("helpLink")}
          </a>
          .
        </p>
      </div>
      <div className="acard">
        <div className="atabs" role="tablist">
          <button type="button" role="tab" aria-selected={tab === "in"} onClick={() => selectTab("in")}>
            {t("tabIn")}
          </button>
          <button type="button" role="tab" aria-selected={tab === "up"} onClick={() => selectTab("up")}>
            {t("tabUp")}
          </button>
        </div>
        <div id="abox">
          {tab === "in" ? (
            forgot ? (
              <ForgotPanel onBack={() => setForgot(false)} />
            ) : (
              <LoginPanel onForgot={() => setForgot(true)} />
            )
          ) : (
            <RegisterPanel onRoleChange={setRole} />
          )}
        </div>
      </div>
    </div>
  );
}
