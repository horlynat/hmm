import type { ComponentProps } from "react";
import Image from "next/image";
import { ArrowRight } from "lucide-react";
import { Link } from "@/i18n/navigation";
import { ViewTransitionLink } from "@/components/ui";

interface NextUpCardProps {
  /** Ex. "Article suivant" / "Projet suivant" — annonce ce que ce bloc propose avant le titre. */
  eyebrow: string;
  title: string;
  /** Ex. "Lire l'article" / "Voir les détails". */
  cta: string;
  href: ComponentProps<typeof Link>["href"];
  image?: string;
  imageAlt?: string;
  /** Doit correspondre au nom posé sur l'image de couverture de la page cible (cf. lib/viewTransitionNames.ts), pour que la transition la fasse "morphir" au lieu d'un simple fondu. Sans effet si `image` est absent. */
  imageTransitionName?: string;
}

/**
 * Bloc "suite de la découverte" en fin de page de détail (article, projet) —
 * remplace un simple lien retour par une invitation à continuer, dans le même
 * dégradé que les CTA de clôture des pages liste (realisations/page.tsx).
 * Pensé pour boucler dans la collection (le dernier élément renvoie vers le
 * premier) plutôt que de laisser le visiteur "dans le vide" en fin de page.
 */
export function NextUpCard({ eyebrow, title, cta, href, image, imageAlt, imageTransitionName }: NextUpCardProps) {
  return (
    <ViewTransitionLink
      href={href}
      viewTransitionName={image ? imageTransitionName : undefined}
      className="group flex flex-col overflow-hidden rounded-[var(--radius-md)] shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-lg sm:flex-row"
      style={{ background: "linear-gradient(135deg, var(--cta-gradient-from), var(--cta-gradient-to) 80%)" }}
    >
      {image && (
        <div
          className={imageTransitionName ? "vt-target relative h-[160px] w-full shrink-0 sm:h-auto sm:w-[280px]" : "relative h-[160px] w-full shrink-0 sm:h-auto sm:w-[280px]"}
          style={imageTransitionName ? { viewTransitionName: imageTransitionName } : undefined}
        >
          <Image src={image} alt={imageAlt ?? title} fill sizes="(min-width: 640px) 280px, 100vw" className="object-cover" />
        </div>
      )}
      <div className="flex flex-1 flex-col justify-center gap-1.5 p-6 text-white sm:p-8">
        <span className="text-xs font-bold tracking-wide uppercase opacity-75">{eyebrow}</span>
        <h3 className="text-xl font-semibold sm:text-2xl" style={{ fontFamily: "var(--font-heading)" }}>
          {title}
        </h3>
        <span className="mt-1 inline-flex items-center gap-1.5 text-sm font-semibold">
          {cta}
          <ArrowRight size={16} aria-hidden="true" className="transition-transform group-hover:translate-x-1" />
        </span>
      </div>
    </ViewTransitionLink>
  );
}
