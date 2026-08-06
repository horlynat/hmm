export const siteConfig = {
  name: "Horlynat Mampassi Mbama",
  whatsappUrl: "https://wa.me/242066429090",
  social: {
    linkedin: "https://www.linkedin.com/in/horlynat/",
    github: "https://github.com/horlynat",
    facebook: "https://facebook.com/horlynat92",
  },
};

/** Routes statiques uniquement (sans segment dynamique) — utilisées pour la nav/footer. */
export type NavHref =
  | "/"
  | "/a-propos"
  | "/competences"
  | "/realisations"
  | "/blog"
  | "/freelances"
  | "/contact"
  | "/mentions-legales"
  | "/politique-de-confidentialite"
  | "/politique-de-cookies"
  | "/conditions-generales";

export const navItems: { key: string; href: NavHref }[] = [
  { key: "apropos", href: "/a-propos" },
  { key: "competences", href: "/competences" },
  { key: "realisations", href: "/realisations" },
  { key: "blog", href: "/blog" },
  { key: "contact", href: "/contact" },
];
