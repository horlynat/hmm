import { NextRequest, NextResponse } from "next/server";
import { revalidateTag } from "next/cache";

/**
 * Webhook appelé par le backend Symfony après publication/mise à jour d'un
 * contenu (Project, Article, ...). Cf. plan pour le flux complet.
 *
 * `{ expire: 0 }` et non `"max"` : `"max"` ne fait que marquer le tag comme
 * périmé et attend la prochaine visite pour relancer un fetch en arrière-plan
 * (stale-while-revalidate) — un visiteur peut donc retomber sur du contenu
 * périmé plusieurs fois de suite après l'appel du webhook. Ce endpoint est
 * justement le cas d'usage documenté pour `{ expire: 0 }` (cf.
 * node_modules/next/dist/docs/.../revalidateTag.md) : un système externe
 * (ici le backend) qui a besoin d'une expiration immédiate, sans dépendre
 * d'une visite ultérieure pour déclencher le rafraîchissement.
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

  revalidateTag(tag, { expire: 0 });

  return NextResponse.json({ revalidated: true, tag });
}
