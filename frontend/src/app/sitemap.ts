import type { MetadataRoute } from "next";
import { routing } from "@/i18n/routing";
import { getPathname } from "@/i18n/navigation";
import { getProjects } from "@/lib/api/projects";
import { getArticles } from "@/lib/api/articles";

const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL ?? "https://horlynat.com";

const STATIC_PATHNAMES = [
  "/",
  "/a-propos",
  "/competences",
  "/realisations",
  "/blog",
  "/freelances",
  "/contact",
  "/mentions-legales",
  "/politique-de-confidentialite",
  "/politique-de-cookies",
  "/conditions-generales",
] as const;

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  // Seul `.slug` est utilisé ci-dessous (identique quelle que soit la
  // locale) : la locale par défaut suffit, pas besoin de résoudre la vraie
  // locale courante (ce fichier génère les URLs de TOUTES les locales).
  const [projects, articles] = await Promise.all([
    getProjects(routing.defaultLocale),
    getArticles(routing.defaultLocale),
  ]);

  const staticEntries: MetadataRoute.Sitemap = STATIC_PATHNAMES.flatMap((pathname) =>
    routing.locales.map((locale) => ({
      url: `${SITE_URL}${getPathname({ locale, href: pathname })}`,
      lastModified: new Date(),
    })),
  );

  const projectEntries: MetadataRoute.Sitemap = projects.flatMap((project) =>
    routing.locales.map((locale) => ({
      url: `${SITE_URL}${getPathname({
        locale,
        href: { pathname: "/realisations/[slug]", params: { slug: project.slug } },
      })}`,
      lastModified: new Date(),
    })),
  );

  const articleEntries: MetadataRoute.Sitemap = articles.flatMap((article) =>
    routing.locales.map((locale) => ({
      url: `${SITE_URL}${getPathname({
        locale,
        href: { pathname: "/blog/[slug]", params: { slug: article.slug } },
      })}`,
      lastModified: new Date(),
    })),
  );

  return [...staticEntries, ...projectEntries, ...articleEntries];
}
