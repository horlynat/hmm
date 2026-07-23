export const siteConfig = {
  whatsappUrl: "https://wa.me/242000000000",
  social: {
    linkedin: "#",
    github: "#",
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
  | "/mentions-legales";

export const navItems: { key: string; href: NavHref }[] = [
  { key: "accueil", href: "/" },
  { key: "apropos", href: "/a-propos" },
  { key: "competences", href: "/competences" },
  { key: "realisations", href: "/realisations" },
  { key: "blog", href: "/blog" },
  { key: "freelances", href: "/freelances" },
];
