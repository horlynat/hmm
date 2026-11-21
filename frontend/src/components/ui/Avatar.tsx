import clsx from "clsx";
import { getAvatarImageUrl, getInitials } from "@/lib/media";

interface AvatarProps {
  user: { profileImage?: string | null; fullName?: string | null; email?: string | null };
  /** Dimensions, bordure, anneau… (ex. "h-11 w-11 border"). */
  className?: string;
  /** Taille du texte des initiales (ex. "text-sm"). */
  textClassName?: string;
}

/**
 * Avatar décoratif (le nom est toujours affiché à côté) : photo de profil si
 * elle existe, sinon initiales rendues en HTML/CSS aux couleurs de la marque
 * — aucune requête vers un service tiers, aucune donnée personnelle
 * transmise. Utilisable en Server ET Client Component (aucun hook).
 */
export function Avatar({ user, className, textClassName = "text-xs" }: AvatarProps) {
  const src = getAvatarImageUrl(user);

  if (src) {
    return (
      // eslint-disable-next-line @next/next/no-img-element -- média backend (ou URL de profil externe), hors domaines optimisables par next/image
      <img src={src} alt="" className={clsx("shrink-0 rounded-full object-cover", className)} />
    );
  }

  return (
    <span
      aria-hidden="true"
      className={clsx(
        "inline-flex shrink-0 select-none items-center justify-center rounded-full bg-brand-primary font-bold leading-none text-white",
        textClassName,
        className,
      )}
    >
      {getInitials(user)}
    </span>
  );
}
