export const siteConfig = {
  name: "Horlynat MAMPASSI MBAMA",
  whatsappUrl: "https://wa.me/242066429090",
  /** Lien d'appel du même numéro que `whatsappUrl` (`tel:`) et sa forme lisible. */
  phoneHref: "tel:+242066429090",
  phoneDisplay: "+242 06 642 90 90",
  social: {
    linkedin: "https://www.linkedin.com/in/horlynat/",
    github: "https://github.com/horlynat",
    facebook: "https://facebook.com/horlynat92",
  },
};

/** Routes statiques uniquement (sans segment dynamique) — utilisées pour la nav/footer. */
export type NavHref =
  | "/"
  | "/offres"
  | "/a-propos"
  | "/competences"
  | "/realisations"
  | "/blog"
  | "/freelances"
  | "/aide"
  | "/contact"
  | "/mentions-legales"
  | "/politique-de-confidentialite"
  | "/politique-de-cookies"
  | "/conditions-generales";

// "/aide" reste une route valide (centre d'aide public, accessible depuis
// l'espace client — cf. AccountNav.tsx — ou un lien direct/email de support)
// mais n'apparaît plus ici : plus dans le menu ni le footer du site vitrine.
//
// "/offres" (R4) en première position : c'est désormais la page que
// l'accueil met le plus en avant (hero, besoins, grille), elle doit être
// atteignable depuis n'importe quelle autre page sans repasser par l'accueil.
export const navItems: { key: string; href: NavHref }[] = [
  { key: "offres", href: "/offres" },
  { key: "apropos", href: "/a-propos" },
  { key: "competences", href: "/competences" },
  { key: "realisations", href: "/realisations" },
  { key: "blog", href: "/blog" },
  { key: "contact", href: "/contact" },
];
