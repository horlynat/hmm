import { getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";
import { siteConfig } from "@/config/site";
import { getOffers } from "@/lib/api/offers";

/**
 * Pied de page du site vitrine — markup du prototype (`.ft .ftg .ftb`),
 * alimenté par le back-office :
 * colonne « Offres » = offres réellement publiées (sélection du prototype si
 * elles sont en ligne, sinon les premières par ordre d'affichage — jamais de
 * lien vers une offre dépubliée). Textes bilingues, année courante.
 * Liens réseaux sociaux ajoutés à la demande de l'utilisateur (absents du
 * prototype), discrets, sous la description.
 *
 * Composant serveur, hors du fichier d'index components/layout (qui est aussi
 * importé côté client) : il appelle l'API, réservée au serveur.
 */
const PROTOTYPE_SELECTION = ["whatsapp", "architecture", "audit", "risques"];

export async function SiteFooter({ locale }: { locale: string }) {
  const t = await getTranslations({ locale, namespace: "landing.footer" });
  const offers = await getOffers(locale);
  const selected = PROTOTYPE_SELECTION.map((slug) => offers.find((o) => o.slug === slug)).filter((o) => o !== undefined);
  const footerOffers = (selected.length === PROTOTYPE_SELECTION.length ? selected : offers).slice(0, 4);
  const year = new Date().getFullYear();

  const social = [
    { href: siteConfig.social.linkedin, label: "LinkedIn", d: "M4.98 3.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5zM3 9h4v12H3zM9 9h3.8v1.7h.06c.53-1 1.83-2.06 3.77-2.06C20.6 8.64 21 11.3 21 14.7V21h-4v-5.6c0-1.34-.03-3.06-1.86-3.06-1.87 0-2.15 1.46-2.15 2.96V21H9z" },
    { href: siteConfig.social.github, label: "GitHub", d: "M12 2a10 10 0 0 0-3.16 19.49c.5.09.68-.22.68-.48v-1.7c-2.78.6-3.37-1.34-3.37-1.34-.45-1.16-1.1-1.47-1.1-1.47-.9-.62.07-.6.07-.6 1 .07 1.53 1.03 1.53 1.03.89 1.53 2.34 1.09 2.91.83.09-.65.35-1.09.63-1.34-2.22-.25-4.55-1.11-4.55-4.94 0-1.09.39-1.98 1.03-2.68-.1-.25-.45-1.27.1-2.65 0 0 .84-.27 2.75 1.02a9.56 9.56 0 0 1 5 0c1.91-1.29 2.75-1.02 2.75-1.02.55 1.38.2 2.4.1 2.65.64.7 1.03 1.59 1.03 2.68 0 3.84-2.34 4.69-4.57 4.93.36.31.68.92.68 1.85v2.75c0 .27.18.58.69.48A10 10 0 0 0 12 2z" },
    { href: siteConfig.social.facebook, label: "Facebook", d: "M22 12.06C22 6.5 17.52 2 12 2S2 6.5 2 12.06c0 5.02 3.66 9.18 8.44 9.94v-7.03H7.9v-2.91h2.54V9.85c0-2.51 1.49-3.9 3.78-3.9 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56v1.88h2.77l-.44 2.91h-2.33V22c4.78-.76 8.43-4.92 8.43-9.94z" },
  ];

  return (
    <footer className="ft">
      <div className="w">
        <div className="ftg">
          <div>
            <Link className="brand" href="/" aria-label={t("home")}>
              <span className="logo">H</span>Horlynat
            </Link>
            <p className="mut" style={{ marginTop: "16px", fontSize: "14.5px", maxWidth: "34ch" }}>
              {t("tagline")}
            </p>
            <div aria-label={t("social")} role="group" style={{ display: "flex", gap: "14px", marginTop: "18px" }}>
              {social.map((s) => (
                <a key={s.label} href={s.href} target="_blank" rel="noopener noreferrer" aria-label={s.label} className="mut" style={{ display: "inline-flex" }}>
                  <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                    <path d={s.d} />
                  </svg>
                </a>
              ))}
            </div>
          </div>
          <div>
            <h4>{t("offers")}</h4>
            {footerOffers.map((o) => (
              <Link key={o.slug} href={{ pathname: "/offres/[slug]", params: { slug: o.slug } }}>
                {o.title}
              </Link>
            ))}
            <Link href="/offres">{t("allOffers")}</Link>
          </div>
          <div>
            <h4>{t("site")}</h4>
            <Link href="/a-propos">{t("about")}</Link>
            <Link href={{ pathname: "/a-propos", hash: "competences" }}>{t("skills")}</Link>
            <Link href={{ pathname: "/a-propos", hash: "realisations" }}>{t("projects")}</Link>
            <Link href="/blog">{t("blog")}</Link>
          </div>
          <div>
            <h4>{t("legal")}</h4>
            <Link href="/mentions-legales">{t("mentions")}</Link>
            <Link href="/politique-de-confidentialite">{t("privacy")}</Link>
            <Link href="/conditions-generales">{t("terms")}</Link>
            <Link href="/conditions-de-prestation">{t("service")}</Link>
            <Link href="/politique-de-cookies">{t("cookies")}</Link>
          </div>
          <div>
            <h4>{t("contact")}</h4>
            <a href={siteConfig.whatsappUrl} target="_blank" rel="noopener noreferrer">WhatsApp</a>
            <a href="mailto:contact@horlynat.com">contact@horlynat.com</a>
            <a href="https://groupdbg.com" target="_blank" rel="noopener noreferrer">{t("dbg")}</a>
          </div>
        </div>
        <div className="ftb">
          <span>© {year} Horlynat. {t("rights")}</span>
          <span>{t("location")}</span>
        </div>
      </div>
    </footer>
  );
}
