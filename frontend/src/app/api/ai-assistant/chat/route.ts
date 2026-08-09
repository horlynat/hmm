import { NextRequest, NextResponse } from "next/server";
import { API_URL } from "@/lib/auth/config";
import { rateLimit } from "@/lib/rate-limit";
import type { AiAssistantChatPayload } from "@/lib/types";

/**
 * Proxy public (pas d'authentification requise) vers POST /api/assistant/chat.
 * Ne réutilise pas apiPost() de lib/api/client.ts : celui-ci ne renvoie que
 * {ok:true} sans le corps de la réponse, alors qu'on a besoin de `answer`.
 *
 * Deux filtres avant d'atteindre le backend :
 *  1. rate-limit en mémoire ici (premier filet, gratuit) ;
 *  2. le rate limiter Symfony `ai_assistant_chat` (coûte réellement de
 *     l'argent par appel accepté) — qui a besoin de la vraie IP du visiteur,
 *     pas celle du conteneur frontend : d'où le forward explicite de
 *     X-Forwarded-For ci-dessous (SYMFONY_TRUSTED_PROXIES couvre déjà le
 *     réseau Docker interne, cf. main/infra/README.md).
 */
export async function POST(request: NextRequest) {
  if (!(await rateLimit("ai-assistant-chat", 20, 60 * 60 * 1000))) {
    return NextResponse.json({ ok: false, error: "rate_limited" }, { status: 429 });
  }

  const body = (await request.json().catch(() => null)) as AiAssistantChatPayload | null;
  if (!body || typeof body.question !== "string" || !body.question.trim()) {
    return NextResponse.json({ ok: false, error: "invalid_input" }, { status: 400 });
  }

  const forwardedFor =
    request.headers.get("x-forwarded-for") ?? request.headers.get("x-real-ip") ?? "";

  try {
    const res = await fetch(`${API_URL}/assistant/chat`, {
      method: "POST",
      headers: {
        "Content-Type": "application/ld+json",
        Accept: "application/ld+json",
        ...(forwardedFor ? { "X-Forwarded-For": forwardedFor } : {}),
      },
      body: JSON.stringify({
        question: body.question,
        history: Array.isArray(body.history) ? body.history : [],
        locale: body.locale ?? "fr",
      }),
      cache: "no-store",
      signal: AbortSignal.timeout(15_000),
    });

    if (429 === res.status) {
      return NextResponse.json({ ok: false, error: "rate_limited" }, { status: 429 });
    }
    if (503 === res.status) {
      return NextResponse.json({ ok: false, error: "unavailable" }, { status: 503 });
    }
    if (!res.ok) {
      console.error(`[ai-assistant] POST /assistant/chat -> ${res.status} ${res.statusText}`);
      return NextResponse.json({ ok: false, error: "unavailable" }, { status: 502 });
    }

    const data = (await res.json()) as { answer?: string; suggestions?: string[] };
    if (!data.answer) {
      return NextResponse.json({ ok: false, error: "unavailable" }, { status: 502 });
    }

    return NextResponse.json({
      ok: true,
      answer: data.answer,
      suggestions: Array.isArray(data.suggestions) ? data.suggestions : [],
    });
  } catch (error) {
    console.error("[ai-assistant] POST /assistant/chat failed", error);
    return NextResponse.json({ ok: false, error: "network_error" }, { status: 502 });
  }
}
