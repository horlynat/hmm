import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { getOffers } from "@/lib/api/offers";
import { buildPageMetadata } from "@/lib/metadata";
import { OffersCatalog } from "@/components/offers-proto/OffersCatalog";
import { ProtoCta, ProtoPageHead } from "@/components/proto/ProtoSections";

export const dynamic = "force-static";

export async function generateMetadata({ params }: { params: Promise<{ locale: string }> }): Promise<Metadata> {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: "catalog" });
  return buildPageMetadata({ locale, pathname: "/offres", title: t("metaTitle"), description: t("lede") });
}

export default async function OffresPage({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  const [t, offers] = await Promise.all([getTranslations({ locale, namespace: "catalog" }), getOffers(locale)]);

  return (
    <>
      <ProtoPageHead
        locale={locale}
        crumbs={t("crumb")}
        title={
          <>
            {t("title")}
            <span className="grad">{t("titleAccent")}</span>
          </>
        }
        lede={t("lede")}
      />
      <section className="sec" style={{ paddingTop: "40px" }}>
        <div className="w">
          {offers.length > 0 ? <OffersCatalog offers={offers} /> : <p className="mut">{t("noOffers")}</p>}
        </div>
      </section>
      <ProtoCta locale={locale} title={t("ctaTitle")} text={t("ctaText")} />
    </>
  );
}
