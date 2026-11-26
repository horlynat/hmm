const MEDIA_URL = process.env.NEXT_PUBLIC_MEDIA_URL ?? "http://127.0.0.1:8000";

/** Résout le chemin relatif d'un `Media.filePath` (API) vers son URL publique complète. */
export function getMediaUrl(filePath: string): string {
  return `${MEDIA_URL}${filePath}`;
}

/**
 * Vrai pour un fichier SVG (y compris `.svgz`, et une éventuelle requête ou
 * ancre après l'extension). Dans ce portfolio, un SVG en couverture de
 * réalisation est toujours un schéma au fond assorti au cadre qui l'affiche,
 * jamais une photo — il s'affiche donc entier (« contain »), sans recadrage ;
 * une image matricielle (une vraie capture) se recadre pour remplir le cadre
 * (« cover »), cf. ProjectImage.
 */
export function isSvgFile(filePath: string): boolean {
  return /\.svgz?(?:$|[?#])/i.test(filePath);
}

/**
 * Photo de profil d'un utilisateur, ou `null` s'il n'en a pas — l'appelant
 * affiche alors des initiales générées localement (composant `Avatar`).
 *
 * Plus aucun repli vers ui-avatars.com : ce service tiers recevait le nom
 * complet (ou l'e-mail) de chaque utilisateur affiché, à chaque affichage —
 * une fuite de données personnelles vers un tiers sans base légale (RGPD,
 * audit 2026-11-20).
 */
export function getAvatarImageUrl(user: { profileImage?: string | null }): string | null {
  if (!user.profileImage) return null;
  return /^https?:\/\//i.test(user.profileImage) ? user.profileImage : getMediaUrl(user.profileImage);
}

/**
 * Initiales (1 à 2 lettres, majuscules) à partir du nom complet, sinon de la
 * partie locale de l'e-mail. Calculées localement — rien n'est envoyé à un tiers.
 */
export function getInitials(user: { fullName?: string | null; email?: string | null }): string {
  const source = (user.fullName ?? "").trim() || (user.email ?? "").split("@")[0].trim();
  const words = source
    .split(/[\s._-]+/)
    .map((w) => w.replace(/[^\p{L}\p{N}]/gu, ""))
    .filter(Boolean);

  if (words.length === 0) return "?";
  const letters = words.length === 1 ? Array.from(words[0]).slice(0, 2) : [Array.from(words[0])[0], Array.from(words[words.length - 1])[0]];
  return letters.join("").toLocaleUpperCase();
}
