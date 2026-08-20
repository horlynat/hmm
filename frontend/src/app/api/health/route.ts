import { NextResponse } from "next/server";

/**
 * Endpoint de supervision (PM2/systemd, Cloudflare health check, uptime monitor).
 * Ne vérifie que la disponibilité du process Next.js lui-même — pas d'appel à
 * l'API Symfony ici, pour ne pas transformer une route publique en sonde de
 * disponibilité du backend.
 */
export async function GET() {
  return NextResponse.json({ status: "ok" });
}
