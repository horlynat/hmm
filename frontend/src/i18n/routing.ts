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
    "/politique-de-confidentialite": {
      fr: "/politique-de-confidentialite",
      en: "/privacy-policy",
    },
    "/politique-de-cookies": {
      fr: "/politique-de-cookies",
      en: "/cookie-policy",
    },
    "/conditions-generales": {
      fr: "/conditions-generales",
      en: "/terms-of-service",
    },
    "/connexion": {
      fr: "/connexion",
      en: "/login",
    },
    "/inscription": {
      fr: "/inscription",
      en: "/register",
    },
    "/compte": {
      fr: "/compte",
      en: "/account",
    },
    "/compte/profil": {
      fr: "/compte/profil",
      en: "/account/profile",
    },
  },
});

export type Pathname = keyof typeof routing.pathnames;
