import Image from "next/image";
import type { CSSProperties } from "react";
import type { Article } from "@/lib/types";
import { getMediaUrl } from "@/lib/media";

/**
 * Vignette d'article du prototype (`.cov`, variable CSS `--h` : teinte du
 * fond en l'absence d'illustration, `hsl(var(--h) 50% 20%)`). Avec une
 * image de couverture réelle (back-office), elle remplit le cadre ; sinon
 * une teinte stable dérivée du slug reprend l'effet du prototype, sans
 * inventer d'illustration.
 */
function hueFromSlug(slug: string): number {
  let hash = 0;
  for (const ch of slug) hash = (hash * 31 + ch.charCodeAt(0)) % 360;
  return hash;
}

export function BlogCover({ article, large, sizes }: { article: Article; large?: boolean; sizes: string }) {
  const cover = article.media[0];
  return (
    <div className={large ? "cov lg" : "cov"} style={{ "--h": hueFromSlug(article.slug) } as CSSProperties}>
      {cover && <Image src={getMediaUrl(cover.filePath)} alt={cover.altText ?? ""} fill sizes={sizes} />}
    </div>
  );
}
