import { getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";

/** Page 404 du site vitrine — markup du prototype (`.ph .nf .nfl`), bilingue. */
export async function SiteNotFound({ locale }: { locale: string }) {
  const t = await getTranslations({ locale, namespace: "notFoundPage" });
  return (
    <section className="ph">
      <div className="w nf">
        <p className="crumb">
          <Link href="/">{t("crumb")}</Link>
        </p>
        <h1>{t("title")}</h1>
        <p>{t("lede")}</p>
        <div className="nfl">
          <Link className="b b-p" href="/">
            {t("home")}
          </Link>
          <Link className="b b-g" href="/offres">
            {t("offers")}
          </Link>
          <Link className="b b-g" href="/contact">
            {t("contact")}
          </Link>
        </div>
      </div>
    </section>
  );
}
