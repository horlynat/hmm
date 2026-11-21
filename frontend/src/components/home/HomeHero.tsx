import { getTranslations } from "next-intl/server";
import { HeroBackground } from "@/components/ui";
import type { Offer } from "@/lib/types";
import { whatsappLink } from "@/lib/whatsapp";
import { OfferHeroCarousel } from "./OfferHeroCarousel";
import { WhatsappIcon } from "./OfferIcon";

/**
 * Hero de l'accueil (refonte 2026-11) : identité + carrousel des offres
 * marquées « carrousel » dans le back-office.
 *
 * Le h1 porte la proposition de valeur et ne dépend PAS de la diapositive
 * affichée : visible quand il n'y a aucune offre à montrer (repli), masqué
 * visuellement (mais lu par les lecteurs d'écran et les moteurs) quand le
 * carrousel occupe la place — chaque diapositive a alors son propre h2.
 */
export async function HomeHero({ locale, offers }: { locale: string; offers: Offer[] }) {
  const t = await getTranslations({ locale, namespace: "home.hero" });
  const hasCarousel = offers.length > 0;

  return (
    <section className="relative overflow-hidden px-6 pt-14 pb-16">
      <HeroBackground />
      <div className="relative mx-auto max-w-[1120px]">
        <div className="hero-in mb-8 flex items-center gap-3" style={{ animationDelay: "0s" }}>
          <span
            aria-hidden="true"
            className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-sm font-bold text-white"
            style={{ fontFamily: "var(--font-heading)", background: "linear-gradient(135deg, var(--cta-gradient-from), var(--cta-gradient-to))" }}
          >
            HM
          </span>
          <span className="leading-tight">
            <b className="block text-[0.95rem]">{t("byName")}</b>
            <span className="block text-xs text-[var(--color-muted)]">{t("byRole1")}</span>
            <span className="block text-xs text-[var(--color-muted)]">{t("byRole2")}</span>
          </span>
        </div>

        <h1 className={hasCarousel ? "sr-only" : "mb-5 max-w-[22ch] text-[clamp(2rem,4.6vw,3.4rem)] leading-[1.05]"}>
          {t("fallbackTitle")}
        </h1>

        {hasCarousel ? (
          <OfferHeroCarousel offers={offers} />
        ) : (
          <>
            <p className="hero-in mb-7 max-w-[56ch] text-[1.05rem] text-[var(--color-muted)]" style={{ animationDelay: "0.12s" }}>
              {t("fallbackLead")}
            </p>
            <a className="btn-whatsapp hero-in" style={{ animationDelay: "0.2s" }} href={whatsappLink()} target="_blank" rel="noopener noreferrer">
              <WhatsappIcon />
              {t("fallbackCta")}
            </a>
          </>
        )}
      </div>
    </section>
  );
}
