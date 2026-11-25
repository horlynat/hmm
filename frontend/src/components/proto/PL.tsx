import type { ComponentProps } from "react";
import { Link } from "@/i18n/navigation";

type Href = ComponentProps<typeof Link>["href"];

/**
 * Adresse du prototype (routes en hash « #/… ») -> adresse réelle du site,
 * localisée par next-intl (/fr/offres, /en/offers…). Seules des chaînes
 * écrites dans le code (pages copiées du prototype) passent ici.
 */
export function protoToSite(path: string): Href {
  const [beforeHash, hash] = path.split("#");
  // Requête : seule `mode=direct` sur /contact est admise (lien « Envoyer un
  // message » de l'assistant, cf. safeHref) ; toute autre est ignorée. Sans ce
  // découpage, « contact?mode=direct » tombait dans `default` → accueil.
  const [route, query = ""] = beforeHash.split("?");
  const parts = route.split("/").filter(Boolean);
  const withHash = <T extends object>(h: T) => (hash ? { ...h, hash } : h);

  switch (parts[0]) {
    case undefined:
      return withHash({ pathname: "/" as const });
    case "offres":
      return parts[1]
        ? withHash({ pathname: "/offres/[slug]" as const, params: { slug: parts[1] } })
        : withHash({ pathname: "/offres" as const });
    case "a-propos":
      return withHash({ pathname: "/a-propos" as const });
    case "competences":
      return { pathname: "/a-propos", hash: "competences" };
    case "realisations":
      return parts[1]
        ? withHash({ pathname: "/realisations/[slug]" as const, params: { slug: parts[1] } })
        : { pathname: "/a-propos", hash: "realisations" };
    case "blog":
      return parts[1]
        ? withHash({ pathname: "/blog/[slug]" as const, params: { slug: parts[1] } })
        : withHash({ pathname: "/blog" as const });
    case "contact":
      return new URLSearchParams(query).get("mode") === "direct"
        ? withHash({ pathname: "/contact" as const, query: { mode: "direct" } })
        : withHash({ pathname: "/contact" as const });
    case "connexion":
      return withHash({ pathname: "/connexion" as const });
    case "confidentialite":
      return withHash({ pathname: "/politique-de-confidentialite" as const });
    case "mentions-legales":
      return withHash({ pathname: "/mentions-legales" as const });
    case "conditions":
      return withHash({ pathname: "/conditions-de-prestation" as const });
    case "utilisation":
      return withHash({ pathname: "/conditions-generales" as const });
    case "cookies":
      return withHash({ pathname: "/politique-de-cookies" as const });
    default:
      return { pathname: "/" };
  }
}

/** Lien interne des pages copiées du prototype. */
export function PL({ href, ...rest }: Omit<ComponentProps<typeof Link>, "href"> & { href: string }) {
  return <Link href={protoToSite(href)} {...rest} />;
}
