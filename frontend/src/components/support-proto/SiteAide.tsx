import { getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";
import { helpCenterContent } from "@/content/help-center";

/** Centre d'aide — hors prototype, habillé dans son style (`.ph .sec .sh .faq2`). */
export async function SiteAide({ locale }: { locale: string }) {
  const t = await getTranslations({ locale, namespace: "helpCenter" });
  const content = helpCenterContent[locale === "en" ? "en" : "fr"];

  return (
    <>
      <section className="ph">
        <div className="w">
          <p className="crumb">{t("eyebrow")}</p>
          <h1>
            {t("title")} <span className="grad">{t("titleAccent")}</span>
          </h1>
          <p>{t("sub")}</p>
        </div>
      </section>

      <section className="sec" style={{ paddingTop: "40px" }}>
        <div className="w">
          <div className="sh">
            <h2>{t("docsHeading")}</h2>
          </div>
          <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(260px, 1fr))", gap: "18px" }}>
            {content.docs.map((doc) => (
              <div key={doc.title} style={{ border: "1px solid var(--ln)", borderRadius: "16px", padding: "20px 22px", background: "var(--s1)" }}>
                <h3 style={{ fontSize: "17px", marginBottom: "8px" }}>{doc.title}</h3>
                <p className="mut" style={{ fontSize: "14.5px" }}>
                  {doc.body}
                </p>
              </div>
            ))}
          </div>
        </div>
      </section>

      <section className="sec tint">
        <div className="w">
          <div className="sh">
            <h2>{t("faqHeading")}</h2>
          </div>
          <div className="faq2">
            {content.faq.map((entry) => (
              <details key={entry.question}>
                <summary>{entry.question}</summary>
                <p>{entry.answer}</p>
              </details>
            ))}
          </div>
        </div>
      </section>

      <section className="sec" style={{ paddingTop: "40px" }}>
        <div className="w">
          <div className="cta">
            <h2>{t("ctaTitle")}</h2>
            <p>{t("ctaText")}</p>
            <div className="row">
              <Link className="b b-p" href="/support/nouveau">
                {t("ctaButton")}
              </Link>
            </div>
          </div>
        </div>
      </section>
    </>
  );
}
