import { getTranslations } from "next-intl/server";
import { Badge, Reveal } from "@/components/ui";
import { OfferIcon } from "./OfferIcon";

/** Situations dans l'ordre du prototype, et icône associée (cf. Offer::SITUATIONS). */
const NEEDS = [
  { key: "clients", icon: "shop" },
  { key: "appli", icon: "arch" },
  { key: "secu", icon: "lock" },
  { key: "assur", icon: "umb" },
] as const;

/**
 * « Quel problème voulez-vous régler ? » — chaque carte renvoie vers la
 * grille des offres filtrée sur la situation (`#offres-<situation>`, lu par
 * OffersSection). Sans JavaScript, le lien fait simplement défiler jusqu'aux
 * offres (ancres posées dans OffersSection).
 */
export async function NeedsSection({ locale }: { locale: string }) {
  const t = await getTranslations({ locale, namespace: "home.needs" });

  return (
    <section className="px-6 py-20">
      <div className="mx-auto max-w-[1120px]">
        <Reveal className="mb-10 text-center">
          <Badge className="mb-3.5">{t("eyebrow")}</Badge>
          <h2 className="mb-2 text-[clamp(1.75rem,3.5vw,2.5rem)]">{t("title")}</h2>
          <p className="mx-auto max-w-[60ch] text-[var(--color-muted)]">{t("lede")}</p>
        </Reveal>
        <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
          {NEEDS.map(({ key, icon }, i) => (
            <Reveal key={key} delay={i * 0.06}>
              <a href={`#offres-${key}`} className="card group flex h-full flex-col gap-3 no-underline">
                <span className="flex items-center gap-3">
                  <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-primary/15 text-brand-primary">
                    <OfferIcon icon={icon} />
                  </span>
                  <small className="font-mono text-[0.7rem] uppercase tracking-wide text-[var(--color-muted)]">
                    {t(`items.${key}.domain`)}
                  </small>
                </span>
                <b className="text-[1.05rem] leading-snug" style={{ fontFamily: "var(--font-heading)" }}>
                  {t(`items.${key}.title`)}
                </b>
                <span className="text-sm text-[var(--color-muted)]">{t(`items.${key}.desc`)}</span>
                <span className="mt-auto text-sm font-semibold text-brand-primary group-hover:underline">
                  {t("seeSolution")} →
                </span>
              </a>
            </Reveal>
          ))}
        </div>
      </div>
    </section>
  );
}
