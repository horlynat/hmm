import { NextResponse } from "next/server";
import { getToken } from "@/lib/auth/session";
import { API_URL } from "@/lib/auth/config";

/**
 * Export RGPD des données du compte courant : proxy authentifié vers
 * GET /api/me (Bearer token lu depuis le cookie httpOnly, jamais exposé au
 * client), renvoyé en téléchargement direct plutôt qu'en JSON brut affiché.
 */
export async function GET() {
  const token = await getToken();
  if (!token) {
    return NextResponse.json({ message: "unauthenticated" }, { status: 401 });
  }

  const res = await fetch(`${API_URL}/me`, {
    headers: {
      Accept: "application/json",
      Authorization: `Bearer ${token}`,
    },
    cache: "no-store",
  });

  if (!res.ok) {
    return NextResponse.json({ message: "fetch_failed" }, { status: res.status });
  }

  const data = await res.json();

  return new NextResponse(JSON.stringify(data, null, 2), {
    headers: {
      "Content-Type": "application/json; charset=utf-8",
      "Content-Disposition": 'attachment; filename="mes-donnees.json"',
    },
  });
}
