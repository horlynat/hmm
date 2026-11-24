import { getTranslations } from "next-intl/server";
import { whatsappLink } from "@/lib/whatsapp";
import { ProtoIcon } from "@/components/proto/ProtoSvg";
import { FreelanceSignupForm } from "./FreelanceSignupForm";

/**
 * Page de recrutement freelance — hors prototype (qui ne modélise que la
 * création de compte dans /connexion), habillée dans son style : `.ph .sec
 * .sh .pstat .steps6 .eng3 .faq2 .acard`.
 */
export async function SiteFreelances({ locale }: { locale: string }) {
  const t = await getTranslations({ locale, namespace: "freelances" });

  const steps: [string, string][] = [
    [t("how.step1Title"), t("how.step1Desc")],
    [t("how.step2Title"), t("how.step2Desc")],
    [t("how.step3Title"), t("how.step3Desc")],
  ];
  const benefits: [string, string][] = [
    [t("benefits.b1Title"), t("benefits.b1Desc")],
    [t("benefits.b2Title"), t("benefits.b2Desc")],
    [t("benefits.b3Title"), t("benefits.b3Desc")],
    [t("benefits.b4Title"), t("benefits.b4Desc")],
  ];
  const faq: [string, string][] = [
    [t("signup.faq1Q"), t("signup.faq1A")],
    [t("signup.faq2Q"), t("signup.faq2A")],
    [t("signup.faq3Q"), t("signup.faq3A")],
  ];

  return (
    <>
      <section className="ph">
        <div className="w">
          <p className="crumb">{t("eyebrow")}</p>
          <h1>
            {t("title")} <span className="grad">{t("titleAccent")}</span>
          </h1>
          <p>{t("sub")}</p>
          <div className="pstat sm" style={{ maxWidth: "520px" }}>
            <div>
              <b>100%</b>
              <span>{t("stats.qualified")}</span>
            </div>
            <div>
              <b>1</b>
              <span>{t("stats.contact")}</span>
            </div>
            <div>
              <b>∞</b>
              <span>{t("stats.open")}</span>
            </div>
          </div>
          <div style={{ display: "flex", gap: "12px", flexWrap: "wrap", marginTop: "10px" }}>
            <a className="b b-g" href="#signup">
              {t("ctaCreate")}
            </a>
            <a className="b b-w" href={whatsappLink(t("whatsappMessage"))} target="_blank" rel="noopener noreferrer">
              <ProtoIcon name="wa" />
              {t("whatsapp")}
            </a>
          </div>
        </div>
      </section>

      <section className="sec" id="how">
        <div className="w">
          <div className="sh">
            <span className="kick">{t("how.eyebrow")}</span>
            <h2>{t("how.title")}</h2>
            <p>{t("how.lede")}</p>
          </div>
          <ol className="steps6">
            {steps.map(([b, p], i) => (
              <li key={b}>
                <span className="n">{i + 1}</span>
                <b>{b}</b>
                <p>{p}</p>
              </li>
            ))}
          </ol>
        </div>
      </section>

      <section className="sec tint">
        <div className="w">
          <div className="sh">
            <span className="kick">{t("benefits.eyebrow")}</span>
            <h2>{t("benefits.title")}</h2>
          </div>
          <div className="eng3">
            {benefits.map(([b, s]) => (
              <div key={b}>
                <b>{b}</b>
                <span>{s}</span>
              </div>
            ))}
          </div>
        </div>
      </section>

      <section className="sec" id="signup">
        <div className="w od">
          <div className="odm">
            <div className="sh" style={{ textAlign: "left" }}>
              <span className="kick">{t("signup.eyebrow")}</span>
              <h2>{t("signup.title")}</h2>
              <p>{t("signup.lede")}</p>
            </div>
            <div className="faq2" style={{ gridTemplateColumns: "1fr", marginTop: "24px" }}>
              {faq.map(([q, a]) => (
                <details key={q}>
                  <summary>{q}</summary>
                  <p>{a}</p>
                </details>
              ))}
            </div>
          </div>
          <aside className="aside">
            <div className="acard">
              <div id="abox">
                <FreelanceSignupForm />
              </div>
            </div>
          </aside>
        </div>
      </section>
    </>
  );
}
