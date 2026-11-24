import Image from "next/image";
import type { CSSProperties } from "react";
import type { Article } from "@/lib/types";
import { getMediaUrl } from "@/lib/media";
import { PROTO_ICONS } from "@/components/proto/assets";
import { articleVisual } from "./blogThemes";

/**
 * Vignette d'article du prototype (`cover()` : `.cov`, dégradé
 * `hsl(h 70% 42%) → hsl(h 60% 14%)`, cinq cercles concentriques et
 * l'icône de la catégorie, `BCAT`). Avec une image de couverture réelle
 * (back-office), celle-ci remplit le cadre par-dessus l'illustration.
 *
 * L'icône vient de PROTO_ICONS (constante du code source, jamais une donnée
 * du back-office) : son injection est donc sûre.
 */
function iconInner(name: keyof typeof PROTO_ICONS): string {
  return PROTO_ICONS[name].replace(/^\s*<svg[^>]*>|<\/svg>\s*$/g, "");
}

export function BlogCover({
  article,
  large,
  sizes,
  alignLeft,
}: {
  article: Article;
  large?: boolean;
  sizes: string;
  /** En-tête d'article : le prototype cadre l'illustration à gauche (`xMinYMid`). */
  alignLeft?: boolean;
}) {
  const cover = article.media[0];
  const [icon, h] = articleVisual(article);
  const gid = `cv-${article.slug.replace(/[^a-zA-Z0-9-]/g, "")}-${large ? "b" : "s"}`;
  return (
    <div className={large ? "cov lg" : "cov"} style={{ "--h": h } as CSSProperties}>
      <svg viewBox="0 0 400 240" preserveAspectRatio={alignLeft ? "xMinYMid slice" : "xMidYMid slice"} aria-hidden="true">
        <defs>
          <linearGradient id={gid} x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stopColor={`hsl(${h} 70% 42%)`} />
            <stop offset="1" stopColor={`hsl(${h} 60% 14%)`} />
          </linearGradient>
        </defs>
        <rect width="400" height="240" fill={`url(#${gid})`} />
        {[0, 1, 2, 3, 4].map((i) => (
          <circle key={i} cx={320 - i * 22} cy={60 + i * 8} r={110 - i * 18} fill="none" stroke={`rgba(255,255,255,${(0.05 + i * 0.02).toFixed(2)})`} />
        ))}
        <g
          transform={`translate(36,${large ? 120 : 110}) scale(3)`}
          fill="none"
          stroke="rgba(255,255,255,.92)"
          strokeWidth="1.6"
          strokeLinecap="round"
          strokeLinejoin="round"
          dangerouslySetInnerHTML={{ __html: iconInner(icon) }}
        />
      </svg>
      {cover && <Image src={getMediaUrl(cover.filePath)} alt={cover.altText ?? ""} fill sizes={sizes} />}
    </div>
  );
}
