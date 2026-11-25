import { getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";
import { getCurrentUser } from "@/lib/auth/session";
import { getOffers } from "@/lib/api/offers";
import { whatsappLink } from "@/lib/whatsapp";
import { siteConfig } from "@/config/site";
import { ProtoIcon, PersonAvatarArt } from "@/components/proto/ProtoSvg";
import { ContactSegments } from "./ContactSegments";

/**
 * Page Contact — markup du prototype (`.cpg .cg .cside .me2 .wabig .crow
 * .ci2 .already .cmain .cbox2 .seg .next3`) : colonne de contact direct
 * (WhatsApp, appel, planification, e-mail, réels) et questionnaire guidé /
 * message rapide (`ContactSegments`, branchés sur les actions existantes).
 */
export async function SiteContact({
  locale,
  offerSlug,
  initialMode = "quest",
}: {
  locale: string;
  offerSlug?: string;
  initialMode?: "quest" | "direct";
}) {
  const [t, tc, user, offers] = await Promise.all([
    getTranslations({ locale, namespace: "contactPage" }),
    getTranslations({ locale, namespace: "catalog" }),
    getCurrentUser(),
    getOffers(locale),
  ]);
  const offerContext = offerSlug ? offers.find((o) => o.slug === offerSlug) : undefined;

  return (
    <section className="cpg">
      <div className="w cg">
        <div className="cside">
          <p className="crumb">
            <Link href="/">{tc("crumbHome")}</Link> / {t("crumb")}
          </p>
          <h1>
            {t("title")}
            <span className="grad">{t("titleAccent")}</span>
          </h1>
          <p className="lead2">{t("lede")}</p>
          <div className="me2">
            <span className="avs" aria-hidden="true">
              <PersonAvatarArt />
            </span>
            <span>
              <b>{siteConfig.name}</b>
              <small>
                <i className="on" />
                {t("available")}
              </small>
            </span>
          </div>
          <a className="wabig" href={whatsappLink(t("whatsappMessage"))} target="_blank" rel="noopener noreferrer">
            <span className="wi">
              <ProtoIcon name="wa" />
            </span>
            <span>
              <b>{t("whatsappTitle")}</b>
              <small>{t("whatsappSub")}</small>
            </span>
            <ProtoIcon name="ar" />
          </a>
          <div className="crow">
            <a href={siteConfig.phoneHref}>
              <span className="ci2">
                <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.362 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.338 1.85.573 2.81.7A2 2 0 0 1 22 16.92z" />
                </svg>
              </span>
              <span>
                <b>{t("callTitle")}</b>
                <small>{siteConfig.phoneDisplay}</small>
                <small className="sub2">
                  {t("callHours")}
                </small>
              </span>
            </a>
            <a href={whatsappLink(t("scheduleMessage"))} target="_blank" rel="noopener noreferrer">
              <span className="ci2">
                <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  <rect x="3" y="4" width="18" height="18" rx="2" />
                  <path d="M16 2v4M8 2v4M3 10h18" />
                </svg>
              </span>
              <span>
                <b>{t("scheduleTitle")}</b>
                <small>{t("scheduleSub")}</small>
              </span>
            </a>
            <a href="mailto:contact@horlynat.com">
              <span className="ci2">
                <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  <rect x="2" y="4" width="20" height="16" rx="2" />
                  <path d="m22 6-10 7L2 6" />
                </svg>
              </span>
              <span>
                <b>{t("emailTitle")}</b>
                <small>contact@horlynat.com</small>
                <small className="sub2">{t("emailDelay")}</small>
              </span>
            </a>
          </div>
          <p className="already">
            {t("alreadyClient")} <Link className="lnk" href="/connexion">{t("accessSpace")}</Link> · {t("alreadyFreelance")}{" "}
            <Link className="lnk" href="/connexion">{t("joinNetwork")}</Link>
          </p>
        </div>
        <div className="cmain">
          <div className="cbox2">
            {offerContext && (
              <p className="asec" style={{ margin: "20px 20px 0" }}>
                {t("offerContext", { title: offerContext.title })}
              </p>
            )}
            <ContactSegments initialName={user?.fullName ?? undefined} initialEmail={user?.email} offers={offers} initialMode={initialMode} />
          </div>
          <ol className="next3">
            <li>
              <span>1</span>
              <b>{t("next1Title")}</b>
              <small>{t("next1Text")}</small>
            </li>
            <li>
              <span>2</span>
              <b>{t("next2Title")}</b>
              <small>{t("next2Text")}</small>
            </li>
            <li>
              <span>3</span>
              <b>{t("next3Title")}</b>
              <small>{t("next3Text")}</small>
            </li>
          </ol>
        </div>
      </div>
    </section>
  );
}
