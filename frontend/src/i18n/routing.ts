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
    "/mot-de-passe-oublie": {
      fr: "/mot-de-passe-oublie",
      en: "/forgot-password",
    },
    "/reinitialiser-mot-de-passe/[token]": {
      fr: "/reinitialiser-mot-de-passe/[token]",
      en: "/reset-password/[token]",
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
    "/compte/projets": {
      fr: "/compte/projets",
      en: "/account/projects",
    },
    "/compte/projets/[id]": {
      fr: "/compte/projets/[id]",
      en: "/account/projects/[id]",
    },
    "/compte/devis": {
      fr: "/compte/devis",
      en: "/account/quotes",
    },
    "/compte/devis/[id]": {
      fr: "/compte/devis/[id]",
      en: "/account/quotes/[id]",
    },
    "/compte/gestion-projet": {
      fr: "/compte/gestion-projet",
      en: "/account/project-management",
    },
    "/compte/factures": {
      fr: "/compte/factures",
      en: "/account/invoices",
    },
    "/compte/mot-de-passe": {
      fr: "/compte/mot-de-passe",
      en: "/account/password",
    },
    "/compte/securite": {
      fr: "/compte/securite",
      en: "/account/security",
    },
    "/compte/parametres": {
      fr: "/compte/parametres",
      en: "/account/settings",
    },
    "/compte/export": {
      fr: "/compte/export",
      en: "/account/export",
    },
    "/compte/supprimer": {
      fr: "/compte/supprimer",
      en: "/account/delete",
    },
  },
});

export type Pathname = keyof typeof routing.pathnames;
