import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { Reveal } from "@/components/ui";
import { PageHero } from "@/components/sections/PageHero";
import { OffresGrid } from "@/components/offres/OffresGrid";
import { TrustBar } from "@/components/home/TrustBar";
import { WhatsappIcon } from "@/components/home/OfferIcon";
import { getOffers } from "@/lib/api/offers";
import { whatsappLink } from "@/lib/whatsapp";
import { buildPageMetadata } from "@/lib/metadata";

export const dynamic = "force-static";

export async function generateMetadata({
  params,
}: {
  params: Promise<{ locale: string }>;
}): Promise<Metadata> {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: "offers" });
  return buildPageMetadata({
    locale,
    pathname: "/offres",
    title: `${t("title")} ${t("titleAccent")}`,
    description: t("sub"),
  });
}

export default async function OffresPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const [t, offers] = await Promise.all([
    getTranslations({ locale, namespace: "offers" }),
    getOffers(locale),
  ]);

  return (
    <>
      <PageHero
        eyebrow={t("eyebrow")}
        title={t("title")}
        titleAccent={t("titleAccent")}
        subtitle={t("sub")}
        subtitleClassName="max-w-[56ch]"
        aside={
          <div className="hero-in soft-card p-6 text-center" style={{ animationDelay: "0.16s" }}>
            <div className="text-[clamp(2.5rem,6vw,3.5rem)] font-extrabold text-brand-primary" style={{ fontFamily: "var(--font-heading)" }}>
              {offers.length}
            </div>
            <p className="text-sm font-semibold uppercase tracking-wide text-[var(--color-muted)]">{t("activeCount")}</p>
          </div>
        }
      />

      {/* Mêmes garanties que l'accueil (components/home/TrustBar.tsx) : un
          visiteur qui atterrit directement ici (lien partagé, recherche)
          doit les voir aussi, pas seulement celui qui passe par la home. */}
      <TrustBar locale={locale} />

      <section className="px-6 py-16">
        <div className="mx-auto max-w-[1120px]">
          {offers.length === 0 ? (
            <Reveal delay={0}>
              <p className="text-sm text-[var(--color-muted)]">{t("empty")}</p>
            </Reveal>
          ) : (
            <Reveal delay={0}>
              <OffresGrid offers={offers} />
            </Reveal>
          )}
        </div>
      </section>

      <section
        className="px-6 py-16 text-center text-white"
        style={{ background: "linear-gradient(135deg, var(--cta-gradient-from), var(--cta-gradient-to) 80%)" }}
      >
        <div className="mx-auto max-w-[1120px]">
          <Reveal delay={0}>
            <h2 className="mb-3 text-[clamp(1.75rem,3.5vw,2.5rem)] text-white">{t("cta.title")}</h2>
            {/* text-center explicite : la règle globale `p { text-align:
                justify }` (globals.css) l'emporterait sur l'héritage. */}
            <p className="mx-auto mb-7 max-w-[56ch] text-center opacity-85">{t("cta.sub")}</p>
            {/* Pas de surcharge de couleur ici (contrairement aux autres CTA de
                clôture, boutons blancs sur le dégradé) : le vert WhatsApp est
                le repère visuel de ce bouton sur tout le site, à garder même
                sur fond dégradé — il reste lisible dessus. */}
            <a
              className="btn-whatsapp mx-auto w-fit"
              href={whatsappLink()}
              target="_blank"
              rel="noopener noreferrer"
            >
              <WhatsappIcon />
              {t("detail.orderCta")}
            </a>
          </Reveal>
        </div>
      </section>
    </>
  );
}
