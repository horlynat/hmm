import { defineRouting } from "next-intl/routing";

export const routing = defineRouting({
  locales: ["fr", "en"],
  defaultLocale: "fr",
  pathnames: {
    "/": "/",
    "/a-propos": {
      fr: "/a-propos",
      en: "/about",
    },
    "/competences": {
      fr: "/competences",
      en: "/skills",
    },
    "/realisations": {
      fr: "/realisations",
      en: "/projects",
    },
    "/realisations/[slug]": {
      fr: "/realisations/[slug]",
      en: "/projects/[slug]",
    },
    "/blog": "/blog",
    "/blog/[slug]": "/blog/[slug]",
    "/freelances": "/freelances",
    "/contact": "/contact",
    "/mentions-legales": {
      fr: "/mentions-legales",
      en: "/legal-notice",
    },
  },
});

export type Pathname = keyof typeof routing.pathnames;
