const MEDIA_URL = process.env.NEXT_PUBLIC_MEDIA_URL ?? "http://127.0.0.1:8000";

/** Résout le chemin relatif d'un `Media.filePath` (API) vers son URL publique complète. */
export function getMediaUrl(filePath: string): string {
  return `${MEDIA_URL}${filePath}`;
}

/**
 * Avatar d'un utilisateur : photo de profil si définie, sinon des initiales
 * générées via ui-avatars.com — même repli que les templates Twig du
 * back-office (`user.profileImage ?? 'https://ui-avatars.com/api/?name=...'`),
 * mais aux couleurs de la marque (brand-primary #0077b6) plutôt qu'au gris par
 * défaut du service, pour rester cohérent avec les badges/boutons de l'espace.
 */
export function getAvatarUrl(user: {
  profileImage?: string | null;
  fullName?: string | null;
  email: string;
}): string {
  if (user.profileImage) {
    return user.profileImage.startsWith("http")
      ? user.profileImage
      : getMediaUrl(user.profileImage);
  }

  const name = encodeURIComponent(user.fullName ?? user.email);
  return `https://ui-avatars.com/api/?name=${name}&background=0077B6&color=fff&bold=true`;
}
