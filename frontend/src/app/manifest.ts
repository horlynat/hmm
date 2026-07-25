import type { MetadataRoute } from "next";

export default function manifest(): MetadataRoute.Manifest {
  return {
    name: "Horlynat — Développeur Full-Stack",
    short_name: "Horlynat",
    description:
      "Portfolio de Horlynat Mampassi Mbama — développeur full-stack, mobile et intégrateur de solutions IA, consultant en cybersécurité et technicien en assurances.",
    start_url: "/",
    scope: "/",
    display: "standalone",
    background_color: "#F9FAFB",
    theme_color: "#03045E",
    lang: "fr",
    icons: [
      {
        src: "/icon.svg",
        sizes: "any",
        type: "image/svg+xml",
        purpose: "any",
      },
    ],
  };
}
