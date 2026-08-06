import { NextRequest, NextResponse } from "next/server";
import { revalidateTag } from "next/cache";

/**
 * Webhook appelé par le backend Symfony après publication/mise à jour d'un
 * contenu (Project, Article, ...). Cf. plan pour le flux complet.
 */
export async function POST(request: NextRequest) {
  const secret = request.headers.get("x-revalidate-secret");

  if (!secret || secret !== process.env.REVALIDATE_SECRET) {
    return NextResponse.json({ message: "Invalid secret" }, { status: 401 });
  }

  const body = await request.json().catch(() => null);
  const tag = body?.tag;

  if (!tag || typeof tag !== "string") {
    return NextResponse.json({ message: "Missing tag" }, { status: 400 });
  }

  revalidateTag(tag, "max");

  return NextResponse.json({ revalidated: true, tag });
}
