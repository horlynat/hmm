"use client";

import { useEffect } from "react";

const SYSTEM_FONT_STACK =
  "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Inter, sans-serif";

/**
 * Frontière d'erreur racine (dernier recours) : remplace tout le document si le
 * layout racine lui-même échoue. Pas d'accès aux styles globaux ni à l'i18n —
 * styles en ligne et texte en français (locale par défaut). Police système
 * plutôt que `next/font/google` (Turbopack sur Next 16.3 échoue au build sur
 * cette combinaison précise — "Font loader calls must be assigned to a const"
 * malgré un const bien présent, cf. vercel/next.js#86792) : sans incidence
 * ici, cette page ne s'affiche qu'en cas de panne catastrophique du layout
 * racine, la fidélité exacte à Inter n'est pas requise.
 */
export default function GlobalError({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  useEffect(() => {
    console.error(error);
  }, [error]);

  return (
    <html lang="fr">
      <body
        style={{
          margin: 0,
          minHeight: "100vh",
          display: "flex",
          alignItems: "center",
          justifyContent: "center",
          fontFamily: SYSTEM_FONT_STACK,
          background: "#f9fafb",
          color: "#03045e",
          padding: "24px",
        }}
      >
        <div style={{ maxWidth: 480, textAlign: "center" }}>
          <h1 style={{ fontSize: "1.6rem", margin: "0 0 12px" }}>
            Une erreur inattendue est survenue.
          </h1>
          <p style={{ opacity: 0.7, margin: "0 0 24px" }}>
            Quelque chose s&apos;est mal passé. Merci de réessayer.
          </p>
          <button
            type="button"
            onClick={reset}
            style={{
              cursor: "pointer",
              border: "none",
              borderRadius: 6,
              padding: "12px 20px",
              fontSize: "0.95rem",
              fontWeight: 600,
              color: "#fff",
              background: "linear-gradient(135deg, #03045e, #0077b6)",
            }}
          >
            Réessayer
          </button>
        </div>
      </body>
    </html>
  );
}
