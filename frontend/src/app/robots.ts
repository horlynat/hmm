import type { MetadataRoute } from "next";

const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL ?? "https://horlynat.com";

// Routes d'authentification et d'espace client : contenu privé ou redirigé
// vers la connexion, aucune valeur SEO — les exclure évite de gaspiller le
// budget de crawl. `Disallow` matche par préfixe, donc pas besoin d'énumérer
// /compte/profil, /compte/devis/[id], le jeton de /support/[token], etc.
// Chemins repris tels que traduits dans `i18n/routing.ts` — à garder en
// phase avec ce fichier si ces routes changent (peu fréquent).
const DISALLOW = [
  "/fr/connexion",
  "/en/login",
  "/fr/inscription",
  "/en/register",
  "/fr/mot-de-passe-oublie",
  "/en/forgot-password",
  "/fr/reinitialiser-mot-de-passe",
  "/en/reset-password",
  "/fr/verification-email",
  "/en/verify-email",
  "/fr/compte",
  "/en/account",
  "/fr/support",
  "/en/support",
];

export default function robots(): MetadataRoute.Robots {
  return {
    rules: { userAgent: "*", allow: "/", disallow: DISALLOW },
    sitemap: `${SITE_URL}/sitemap.xml`,
  };
}
