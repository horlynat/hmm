const MEDIA_URL = process.env.NEXT_PUBLIC_MEDIA_URL ?? "http://127.0.0.1:8000";

/** Résout le chemin relatif d'un `Media.filePath` (API) vers son URL publique complète. */
export function getMediaUrl(filePath: string): string {
  return `${MEDIA_URL}${filePath}`;
}
