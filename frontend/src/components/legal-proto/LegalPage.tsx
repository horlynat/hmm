import type { ReactNode } from "react";
import { getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";
import type { NavHref } from "@/config/site";

/** Dernière mise à jour du contenu des pages légales (src/content/legal) — à changer à chaque modification. */
export const LEGAL_UPDATED_AT = "2026-11-24";

export interface LegalSection {
  id: string;
  title: string;
  body: ReactNode;
}

/**
 * Gabarit commun des pages légales — markup du prototype (`.ph .legal .toc
 * section`) : sommaire d'ancres, sections, date de mise à jour. Chaque page
 * fournit son propre contenu bilingue (`src/content/legal/*.ts`).
 */
export async function LegalPage({
  locale,
  path,
  crumb,
  title,
  lede,
  sections,
  updatedLabel,
}: {
  locale: string;
  path: NavHref;
  crumb: string;
  title: string;
  lede: string;
  sections: LegalSection[];
  updatedLabel: string;
}) {
  const tc = await getTranslations({ locale, namespace: "catalog" });
  return (
    <>
      <section className="ph">
        <div className="w">
          <p className="crumb">
            <Link href="/">{tc("crumbHome")}</Link> / {crumb}
          </p>
          <h1>{title}</h1>
          <p>{lede}</p>
        </div>
      </section>
      <section className="sec" style={{ paddingTop: "56px" }}>
        <div className="w legal">
          <nav className="toc" aria-label="Sommaire">
            {sections.map((s) => (
              <Link key={s.id} href={{ pathname: path, hash: s.id }}>
                {s.title}
              </Link>
            ))}
          </nav>
          <div>
            {sections.map((s) => (
              <section key={s.id} id={s.id}>
                <h2>{s.title}</h2>
                {s.body}
              </section>
            ))}
            <p className="dm" style={{ fontSize: "14px", marginTop: "8px" }}>
              {updatedLabel}
              {locale === "en" ? ": " : " : "}
              <time dateTime={LEGAL_UPDATED_AT}>
                {new Intl.DateTimeFormat(locale === "en" ? "en-GB" : "fr-FR", { dateStyle: "long", timeZone: "UTC" }).format(new Date(LEGAL_UPDATED_AT))}
              </time>
            </p>
          </div>
        </div>
      </section>
    </>
  );
}
