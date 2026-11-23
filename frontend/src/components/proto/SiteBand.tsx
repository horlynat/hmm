"use client";

import { useTranslations } from "next-intl";
import { Link, usePathname } from "@/i18n/navigation";

/**
 * Bandeau « freelance » du prototype (`.fl`, cf. ProtoBand, sa copie
 * conforme), bilingue. Absent de Connexion et Contact, comme footer() du
 * prototype. Le bouton mène à l'inscription réelle (onglets client /
 * freelance), là où le prototype renvoyait vers son écran de connexion.
 */
export function SiteBand() {
  const t = useTranslations("landing.freelance");
  const pathname = usePathname();
  if (pathname === "/connexion" || pathname === "/contact") return null;
  return (
    <section className="fl">
      <div className="w">
        <div>
          <b>{t("title")}</b>
          <p>{t("text")}</p>
        </div>
        <div style={{ display: "flex", gap: "10px", flexWrap: "wrap" }}>
          <Link className="b b-g sm" href="/inscription">
            {t("cta")}
          </Link>
        </div>
      </div>
    </section>
  );
}
