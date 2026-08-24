import type { Metadata } from "next";
import type { ReactNode } from "react";
import { Fraunces, Inter, JetBrains_Mono } from "next/font/google";
import { hasLocale, NextIntlClientProvider } from "next-intl";
import { getMessages, getTranslations, setRequestLocale } from "next-intl/server";
import { headers } from "next/headers";
import { notFound } from "next/navigation";
import { routing } from "@/i18n/routing";
import { InlineScript } from "@/components/ui";
import { siteConfig } from "@/config/site";
import { getHomeContent } from "@/lib/api/home-content";
import { jsonLdScript } from "@/lib/json-ld";
import { buildPageMetadata, SITE_URL } from "@/lib/metadata";
import "../globals.css";

const inter = Inter({ variable: "--font-inter", subsets: ["latin"] });
const jetbrainsMono = JetBrains_Mono({
  variable: "--font-jetbrains-mono",
  subsets: ["latin"],
});
// Serif éditorial réservé au panneau de l'assistant IA (AiAssistantWidget) —
// distinct du sans-serif de marque (--font-heading) utilisé partout ailleurs.
const fraunces = Fraunces({
  variable: "--font-assistant-serif",
  subsets: ["latin"],
  axes: ["opsz", "SOFT"],
});

const THEME_INIT_SCRIPT = `(function(){try{var saved=localStorage.getItem('theme');var wantsDark=saved?saved==='dark':window.matchMedia('(prefers-color-scheme: dark)').matches;if(wantsDark)document.documentElement.classList.add('dark');}catch(e){}})();`;

export function generateStaticParams() {
  return routing.locales.map((locale) => ({ locale }));
}

export async function generateMetadata({
  params,
}: {
  params: Promise<{ locale: string }>;
}): Promise<Metadata> {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: "home" });
  // Contenu piloté par le back-office (App\Entity\HomeContent) ; repli sur
  // les traductions statiques si l'API est momentanément indisponible — une
  // métadonnée dégradée reste préférable à une page qui ne se génère pas.
  const content = await getHomeContent(locale);
  // Titre du site fixé par choix de marque ("Horlynat | Portail Digital")
  // plutôt que dérivé du hero-eyebrow admin — indépendant de la locale.
  const defaultTitle = "Horlynat | Portail Digital";
  const description = content?.heroSub ?? t("sub");

  return {
    // Racine de résolution des URLs relatives (og:image générée par
    // opengraph-image.tsx notamment) — absent jusqu'ici, Next.js le
    // réclamait en warning. Posé ici plutôt que dans buildPageMetadata()
    // pour n'exister qu'une seule fois, au niveau racine.
    metadataBase: new URL(SITE_URL),
    ...buildPageMetadata({ locale, pathname: "/", title: defaultTitle, description }),
    // Le titre garde sa forme "template" (contrairement aux autres pages, qui
    // fournissent une simple string) : c'est ce patron que Next.js applique
    // ensuite au titre de toutes les pages filles.
    title: {
      template: "%s — Horlynat",
      default: defaultTitle,
    },
  };
}

export default async function LocaleLayout({
  children,
  params,
}: {
  children: ReactNode;
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;

  if (!hasLocale(routing.locales, locale)) {
    notFound();
  }

  setRequestLocale(locale);
  // `{ locale }` explicite est indispensable ici : `getMessages()` sans
  // argument est mémoïsé par React `cache()` sur la seule clé `opts?.locale`
  // (toujours `undefined` si on ne le passe pas) — pendant `next build`,
  // plusieurs pages de locales différentes peuvent partager le même worker
  // et donc le même cache `undefined`, ce qui figeait TOUTES les pages
  // statiques sur la première langue générée par ce worker (bug constaté :
  // /en/* rendait du contenu français). `getTranslations({ locale, ... })`
  // n'est pas affecté car il passe déjà `locale` explicitement.
  const messages = await getMessages({ locale });
  const tc = await getTranslations({ locale, namespace: "common" });
  const th = await getTranslations({ locale, namespace: "home" });
  const homeContent = await getHomeContent(locale);
  // `undefined` sur les pages `force-static` : `headers()` y retourne des
  // valeurs vides (cf. doc dynamic="force-static"), pas d'erreur ni de perte
  // du rendu statique. Réel uniquement sur /contact (cf. src/proxy.ts).
  const nonce = (await headers()).get("x-nonce") ?? undefined;

  // `@id` sur les deux schémas : permet à Google de les traiter comme la
  // même entité référencée (Person.worksFor <-> Organization.founder) au
  // lieu de deux blocs dupliqués sans lien explicite entre eux.
  const personJsonLd = {
    "@context": "https://schema.org",
    "@type": "Person",
    "@id": `${SITE_URL}/#person`,
    name: siteConfig.name,
    url: SITE_URL,
    jobTitle: homeContent?.heroEyebrow ?? th("eyebrow"),
    sameAs: [siteConfig.social.linkedin, siteConfig.social.github, siteConfig.social.facebook],
    worksFor: { "@id": `${SITE_URL}/#organization` },
  };

  // Nom repris de `home.founderBadge` ("Fondateur · Digital Business
  // Group") — pas une valeur inventée pour ce schema.
  const organizationJsonLd = {
    "@context": "https://schema.org",
    "@type": "Organization",
    "@id": `${SITE_URL}/#organization`,
    name: "Digital Business Group",
    url: SITE_URL,
    founder: { "@id": `${SITE_URL}/#person` },
  };

  return (
    <html
      lang={locale}
      className={`${inter.variable} ${jetbrainsMono.variable} ${fraunces.variable}`}
      suppressHydrationWarning
    >
      <head>
        <InlineScript html={THEME_INIT_SCRIPT} nonce={nonce} />
        {/* suppressHydrationWarning : les navigateurs masquent l'attribut `nonce`
            après coup (retourne "" via getAttribute) pour empêcher son exfiltration,
            alors que la valeur réelle reste en mémoire — un React hydration
            mismatch inévitable et documenté, pas un vrai bug (cf. guide CSP Next.js). */}
        <script
          type="application/ld+json"
          nonce={nonce}
          suppressHydrationWarning
          dangerouslySetInnerHTML={{ __html: jsonLdScript(personJsonLd) }}
        />
        <script
          type="application/ld+json"
          nonce={nonce}
          suppressHydrationWarning
          dangerouslySetInnerHTML={{ __html: jsonLdScript(organizationJsonLd) }}
        />
      </head>
      <body className="flex min-h-full flex-col antialiased">
        <NextIntlClientProvider locale={locale} messages={messages}>
          <a href="#main-content" className="skip-link">
            {tc("skipToContent")}
          </a>
          {children}
        </NextIntlClientProvider>
      </body>
    </html>
  );
}
