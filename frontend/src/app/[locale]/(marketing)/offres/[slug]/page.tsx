import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { getTranslations } from "next-intl/server";
import { Badge, Breadcrumb, ButtonLink, Card, HeroBackground, Reveal } from "@/components/ui";
import { NextUpCard } from "@/components/sections/NextUpCard";
import { CheckMark, OfferIcon, WhatsappIcon } from "@/components/home/OfferIcon";
import { getOfferBySlug, getOffers, getOfferSlugs } from "@/lib/api/offers";
import { getProjectBySlug } from "@/lib/api/projects";
import { formatFcfa } from "@/lib/format/fcfa";
import { whatsappLink } from "@/lib/whatsapp";
import { resolveOgLocale, SITE_URL } from "@/lib/metadata";
import { siteConfig } from "@/config/site";
import { getPathname } from "@/i18n/navigation";
import { routing } from "@/i18n/routing";

export const dynamic = "force-static";

// Pré-génère une page par offre et par locale au build — même liste d'offres
// quelle que soit la langue (cf. realisations/[slug]/page.tsx).
export async function generateStaticParams() {
  const slugs = await getOfferSlugs();
  return slugs.map((slug) => ({ slug }));
}

export async function generateMetadata({
  params,
}: {
  params: Promise<{ slug: string; locale: string }>;
}): Promise<Metadata> {
  const { slug, locale } = await params;
  const offer = await getOfferBySlug(slug, locale);
  if (!offer) return {};

  const image = `${SITE_URL}/${locale}/opengraph-image`;
  // Canonical et hreflang propres à la fiche : sans eux, la page hérite de
  // ceux du layout racine (l'accueil) et les moteurs la traitent comme un
  // doublon de l'accueil, donc ne l'indexent pas.
  const href = { pathname: "/offres/[slug]" as const, params: { slug: offer.slug } };
  const url = `${SITE_URL}${getPathname({ locale, href })}`;
  const languages = Object.fromEntries(
    routing.locales.map((l) => [l, `${SITE_URL}${getPathname({ locale: l, href })}`]),
  );
  return {
    title: offer.title,
    description: offer.cardText,
    alternates: { canonical: url, languages },
    openGraph: {
      title: offer.title,
      description: offer.cardText,
      url,
      type: "website",
      siteName: siteConfig.name,
      locale: resolveOgLocale(locale),
      images: [image],
    },
    twitter: {
      card: "summary_large_image",
      title: offer.title,
      description: offer.cardText,
      images: [image],
    },
  };
}

function OfferList({ title, items, muted = false }: { title: string; items: string[]; muted?: boolean }) {
  return (
    <div>
      <b className="mb-1.5 block">{title}</b>
      <ul className="list-none space-y-1.5 p-0">
        {items.map((item, i) => (
          <li key={`${i}-${item}`} className="flex items-start gap-2 text-sm text-[var(--color-muted)]">
            <span className={muted ? "text-[var(--color-muted)]" : "text-success"}>
              {muted ? <span aria-hidden="true">–</span> : <CheckMark />}
            </span>
            {item}
          </li>
        ))}
      </ul>
    </div>
  );
}

export default async function OfferDetailPage({
  params,
}: {
  params: Promise<{ slug: string; locale: string }>;
}) {
  const { slug, locale } = await params;
  const [offer, offers, t, tc] = await Promise.all([
    getOfferBySlug(slug, locale),
    getOffers(locale),
    getTranslations({ locale, namespace: "offers" }),
    getTranslations({ locale, namespace: "common" }),
  ]);

  if (!offer) {
    notFound();
  }

  // Offre suivante dans la liste (bouclé), masquée s'il n'y en a qu'une —
  // même logique que ProjectDetailPage.
  const currentIndex = offers.findIndex((o) => o.slug === offer.slug);
  const nextOffer = offers.length > 1 ? offers[(currentIndex + 1) % offers.length] : null;

  // `proofProjectSlug` pointe vers une réalisation publiée qui illustre
  // concrètement cette offre — absent ou slug désynchronisé (projet
  // dépublié/renommé côté back-office) : le lien est simplement omis plutôt
  // que de casser le rendu.
  const proofProject = offer.proofProjectSlug ? await getProjectBySlug(offer.proofProjectSlug, locale) : null;

  const orderHref = whatsappLink(t("detail.orderMessage", { title: offer.title }));

  return (
    <>
      <section className="relative overflow-hidden px-6 pt-14 pb-14">
        <HeroBackground />
        {/* Deux colonnes comme les diapositives du carrousel de l'accueil
            (prototype de refonte) : message à gauche, carte « ce que vous
            recevez » à droite — empilées sous `lg`. */}
        <div className="relative mx-auto grid max-w-[1120px] items-center gap-10 lg:grid-cols-[1.15fr_0.85fr]">
          {/* [&_p]:text-left : sur mobile, la justification globale des <p>
              creuse de grands blancs dans l'accroche et le chapeau. */}
          <div className="[&_p]:text-left">
            <div className="mb-6">
              <Breadcrumb items={[{ label: t("detail.backLabel"), href: "/offres" }, { label: offer.title }]} />
            </div>
            <div className="flex flex-wrap items-center gap-3 mb-4">
              <Badge variant="accent" className="hero-in" style={{ animationDelay: "0s" }}>
                <span className="mr-1.5 inline-flex align-middle"><OfferIcon icon={offer.icon} size={14} /></span>
                {t("eyebrow")}
              </Badge>
              {offer.featured && (
                <span className="hero-in badge-accent" style={{ animationDelay: "0.05s" }}>
                  {t("featured")}
                </span>
              )}
            </div>
            {/* `offer.pain` : une phrase complète saisie en back-office (ex.
                « Vos clients écrivent le soir et restent sans réponse ? ») —
                hors du badge (réservé aux libellés courts en majuscules,
                cf. .badge-accent), comme accroche au-dessus du titre. */}
            <p className="hero-in mb-2 text-sm font-semibold text-brand-primary" style={{ animationDelay: "0.08s" }}>
              {offer.pain}
            </p>
            <h1 className="mb-5 max-w-[32ch] text-[clamp(1.75rem,3vw,2.75rem)] leading-[1.25]">{offer.title}</h1>
            <p className="hero-in mb-7 max-w-[60ch] text-[1.05rem] opacity-75" style={{ animationDelay: "0.16s" }}>
              {offer.leadText}
            </p>
            <div className="hero-in flex flex-wrap items-center gap-x-6 gap-y-3 mb-7" style={{ animationDelay: "0.24s" }}>
              <p className="text-sm text-[var(--color-muted)]">
                {offer.priceOnce ? t("priceOnce") : t("priceFrom")}{" "}
                <b className="text-xl font-extrabold text-brand-dark" style={{ fontFamily: "var(--font-heading)" }}>
                  {formatFcfa(offer.price, locale)}
                </b>
              </p>
              <p className="text-sm text-[var(--color-muted)]">{t("readyIn", { delay: offer.delay })}</p>
            </div>
            <div className="hero-in flex flex-wrap gap-3.5" style={{ animationDelay: "0.32s" }}>
              <a className="btn-whatsapp" href={orderHref} target="_blank" rel="noopener noreferrer">
                <WhatsappIcon />
                {t("detail.orderCta")}
              </a>
              <ButtonLink href="/offres" variant="secondary">
                {t("detail.backLabel")} ←
              </ButtonLink>
            </div>
          </div>

          {/* Pas de `Reveal` ici : contenu du premier écran, visible dès le
              HTML serveur (animation CSS `hero-in` seulement). */}
          {offer.includes.length > 0 && (
            <div className="hero-in soft-card p-7" style={{ animationDelay: "0.2s" }}>
              <div className="mb-5 flex items-center gap-3">
                <span className="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-primary/15 text-brand-primary">
                  <OfferIcon icon={offer.icon} size={24} />
                </span>
                <b className="text-lg" style={{ fontFamily: "var(--font-heading)" }}>{t("detail.includesTitle")}</b>
              </div>
              <ul className="list-none space-y-2.5 p-0">
                {offer.includes.map((item, i) => (
                  <li key={`${i}-${item}`} className="flex items-start gap-2.5 text-sm">
                    <span className="text-success"><CheckMark /></span>
                    {item}
                  </li>
                ))}
              </ul>
            </div>
          )}
        </div>
      </section>

      <section className="border-y border-[var(--border-softer)] bg-bg-card px-6 py-14">
        <div className="mx-auto grid max-w-[1120px] gap-10 md:grid-cols-2 [&_p]:text-left">
          <Reveal delay={0}>
            <div>
              <p className="text-[1.05rem] opacity-80">{offer.cardText}</p>
              <p className="mt-3 text-sm text-[var(--color-muted)]">
                <b className="text-brand-dark">{t("for")}</b> {offer.audience}
              </p>
            </div>
          </Reveal>
          <Reveal delay={0.05}>
            <div>
              <b className="mb-1.5 block">{t("detail.needsTitle")}</b>
              <p className="text-sm text-[var(--color-muted)]">{offer.clientNeeds}</p>
            </div>
          </Reveal>
        </div>
      </section>

      {(offer.beforeItems.length > 0 || offer.afterItems.length > 0) && (
        <section className="px-6 py-14">
          <div className="mx-auto max-w-[1120px] grid gap-6 md:grid-cols-2">
            {offer.beforeItems.length > 0 && (
              <Reveal delay={0}>
                <Card variant="soft" className="h-full p-6">
                  <OfferList title={t("detail.beforeTitle")} items={offer.beforeItems} muted />
                </Card>
              </Reveal>
            )}
            {offer.afterItems.length > 0 && (
              <Reveal delay={0.05}>
                <Card variant="soft" className="h-full p-6">
                  <OfferList title={t("detail.afterTitle")} items={offer.afterItems} />
                </Card>
              </Reveal>
            )}
          </div>
        </section>
      )}

      {offer.pack.length > 0 && (
        <section className="border-y border-[var(--border-softer)] bg-bg-card px-6 py-14">
          <div className="mx-auto max-w-[1120px]">
            <Reveal delay={0}>
              <h2 className="mb-6 text-lg font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
                {t("detail.packTitle")}
              </h2>
              <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                {offer.pack.map((block, i) => (
                  <Reveal key={`${i}-${block.title}`} delay={i * 0.05}>
                    <Card variant="soft" className="h-full p-5">
                      <OfferList title={block.title} items={block.items} />
                    </Card>
                  </Reveal>
                ))}
              </div>
            </Reveal>
          </div>
        </section>
      )}

      {offer.calendar.length > 0 && (
        <section className="px-6 py-14">
          <div className="mx-auto max-w-[1120px]">
            <Reveal delay={0}>
              <h2 className="mb-6 text-lg font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
                {t("detail.calendarTitle")}
              </h2>
              <ol className="list-none space-y-3 p-0">
                {offer.calendar.map((step, i) => (
                  <li key={`${i}-${step.day}`} className="flex gap-4">
                    <span className="w-16 shrink-0 font-mono text-xs font-bold text-brand-primary">{step.day}</span>
                    <span className="text-sm text-[var(--color-muted)]">{step.text}</span>
                  </li>
                ))}
              </ol>
            </Reveal>
          </div>
        </section>
      )}

      {proofProject && (
        <section className="border-y border-[var(--border-softer)] bg-bg-card px-6 py-14">
          <div className="mx-auto max-w-[1120px]">
            <Reveal delay={0}>
              <Badge className="mb-3.5">{t("detail.proofLabel")}</Badge>
              <ButtonLink
                href={{ pathname: "/realisations/[slug]", params: { slug: proofProject.slug } }}
                variant="secondary"
              >
                {proofProject.title} — {t("detail.proofCta")} →
              </ButtonLink>
            </Reveal>
          </div>
        </section>
      )}

      <section className="px-6 py-10">
        <div className="mx-auto max-w-[1120px] border-t border-[var(--border-softer)] pt-6">
          <ButtonLink href="/offres" variant="secondary" className="mb-6">
            {t("eyebrow")} ←
          </ButtonLink>
          {nextOffer && (
            <Reveal delay={0}>
              <NextUpCard
                eyebrow={t("detail.nextOffer")}
                title={nextOffer.title}
                cta={tc("seeDetails")}
                href={{ pathname: "/offres/[slug]", params: { slug: nextOffer.slug } }}
              />
            </Reveal>
          )}
        </div>
      </section>
    </>
  );
}
