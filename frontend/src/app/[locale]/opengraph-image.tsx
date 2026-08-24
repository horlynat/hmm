import { ImageResponse } from "next/og";

// Image de partage (OpenGraph + Twitter Card) générée à la demande par
// Next.js — appliquée par défaut à toutes les pages sous `[locale]` qui ne
// définissent pas la leur (article/projet avec image de couverture, ex.
// blog/[slug]). Doit vivre ICI, sous `[locale]/`, pas à la racine de `app/` :
// Next.js n'a pas de route `app/page.tsx`, seulement `app/[locale]/page.tsx`
// — un fichier `app/opengraph-image.tsx` placé au-dessus du segment
// dynamique n'était jamais résolu (og:image absent des pages, /opengraph-image
// répondait 404). Reprend la palette de marque (`app/globals.css`) et le
// monogramme de `icon.svg`, pas une image statique à maintenir séparément.

export const alt = "Horlynat MAMPASSI MBAMA — Développeur Full-Stack, Mobile & Intégrateur IA";
export const size = { width: 1200, height: 630 };
export const contentType = "image/png";

export default function OpengraphImage() {
  return new ImageResponse(
    (
      <div
        style={{
          width: "100%",
          height: "100%",
          display: "flex",
          flexDirection: "column",
          justifyContent: "center",
          padding: "80px",
          background: "linear-gradient(135deg, #03045E 0%, #0077B6 100%)",
          fontFamily: "sans-serif",
        }}
      >
        <div style={{ display: "flex", alignItems: "center", gap: 20, marginBottom: 48 }}>
          <div
            style={{
              width: 64,
              height: 64,
              borderRadius: 14,
              background: "#03045E",
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              border: "2px solid rgba(255,255,255,0.15)",
            }}
          >
            <svg width="36" height="36" viewBox="0 0 30 30">
              <path
                d="M9 7v16M9 15h6M21 7v16"
                stroke="#F9FAFB"
                strokeWidth="2.6"
                strokeLinecap="round"
                strokeLinejoin="round"
                fill="none"
              />
              <circle cx="21" cy="7" r="2.8" fill="#00B4D8" />
            </svg>
          </div>
          <div style={{ display: "flex", fontSize: 32, color: "#CAF0F8", letterSpacing: 1 }}>HORLYNAT</div>
        </div>
        <div
          style={{
            display: "flex",
            fontSize: 56,
            fontWeight: 700,
            color: "#F9FAFB",
            lineHeight: 1.15,
            maxWidth: 980,
          }}
        >
          Horlynat MAMPASSI MBAMA
        </div>
        <div style={{ display: "flex", fontSize: 30, color: "#CAF0F8", marginTop: 20, maxWidth: 900 }}>
          Développeur Full-Stack, Mobile &amp; Intégrateur IA — Brazzaville, Congo
        </div>
        <div style={{ display: "flex", gap: 12, marginTop: 44 }}>
          {["Symfony", "Next.js", "Flutter", "Cybersécurité"].map((tag) => (
            <div
              key={tag}
              style={{
                display: "flex",
                fontSize: 22,
                color: "#F9FAFB",
                background: "rgba(255,255,255,0.12)",
                padding: "8px 20px",
                borderRadius: 999,
              }}
            >
              {tag}
            </div>
          ))}
        </div>
      </div>
    ),
    { ...size },
  );
}
