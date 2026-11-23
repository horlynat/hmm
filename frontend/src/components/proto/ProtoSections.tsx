import type { ReactNode } from "react";
import { getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";
import { whatsappLink } from "@/lib/whatsapp";
import { ProtoIcon } from "./ProtoSvg";

/**
 * Blocs communs des pages du prototype : en-tête de page (pageHead :
 * `.ph .crumb`) et appel à l'action de bas de page (cta : `.cta`).
 */
export async function ProtoPageHead({
  locale,
  crumbs,
  title,
  lede,
}: {
  locale: string;
  /** Fil d'Ariane après « Accueil » ; le dernier élément est la page courante (texte). */
  crumbs: ReactNode;
  title: ReactNode;
  lede?: ReactNode;
}) {
  const t = await getTranslations({ locale, namespace: "catalog" });
  return (
    <section className="ph">
      <div className="w">
        <p className="crumb">
          <Link href="/">{t("crumbHome")}</Link> / {crumbs}
        </p>
        <h1>{title}</h1>
        {lede && <p>{lede}</p>}
      </div>
    </section>
  );
}

export async function ProtoCta({ locale, title, text }: { locale: string; title?: string; text?: string }) {
  const t = await getTranslations({ locale, namespace: "landing.cta" });
  return (
    <section className="sec" style={{ paddingTop: "40px" }}>
      <div className="w">
        <div className="cta">
          <h2>{title ?? t("title")}</h2>
          <p>{text ?? t("text")}</p>
          <div className="row">
            <a className="b b-w" href={whatsappLink(t("message"))} target="_blank" rel="noopener noreferrer">
              <ProtoIcon name="wa" />
              {t("whatsapp")}
            </a>
            <Link className="b b-g" href="/offres">
              {t("offers")}
            </Link>
          </div>
        </div>
      </div>
    </section>
  );
}
