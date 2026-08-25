import { NextRequest, NextResponse } from "next/server";
import { API_URL } from "@/lib/auth/config";
import { rateLimit } from "@/lib/rate-limit";
import type { AiContentSummaryPayload } from "@/lib/types";

/**
 * Proxy public vers POST /api/assistant/summarize (App\ApiResource\
 * AiContentSummaryApiResource) — endpoint dédié au résumé éditorial d'un
 * article/projet, distinct de /api/ai-assistant/chat (FAQ sur le profil).
 * Même structure que ce dernier (cf. son commentaire pour le détail des deux
 * filtres — rate-limit local ici, X-Forwarded-For pour le rate limiter
 * Symfony) ; même clé de rate-limit ("ai-assistant-chat") : un seul budget
 * de requêtes par visiteur pour l'ensemble de l'assistant, résumé compris.
 */
export async function POST(request: NextRequest) {
  if (!(await rateLimit("ai-assistant-chat", 20, 60 * 60 * 1000))) {
    return NextResponse.json({ ok: false, error: "rate_limited" }, { status: 429 });
  }

  const body = (await request.json().catch(() => null)) as AiContentSummaryPayload | null;
  if (!body || typeof body.slug !== "string" || !body.slug.trim()) {
    return NextResponse.json({ ok: false, error: "invalid_input" }, { status: 400 });
  }

  const forwardedFor =
    request.headers.get("x-forwarded-for") ?? request.headers.get("x-real-ip") ?? "";

  try {
    const res = await fetch(`${API_URL}/assistant/summarize`, {
      method: "POST",
      headers: {
        "Content-Type": "application/ld+json",
        Accept: "application/ld+json",
        ...(forwardedFor ? { "X-Forwarded-For": forwardedFor } : {}),
      },
      body: JSON.stringify({
        slug: body.slug,
        contentType: body.contentType === "project" ? "project" : "article",
        question: typeof body.question === "string" ? body.question : "",
        history: Array.isArray(body.history) ? body.history : [],
        locale: body.locale ?? "fr",
      }),
      cache: "no-store",
      // Doit couvrir le timeout backend (30s, cf. App\Service\ClaudeClient) +
      // une marge de traitement — même raison que /api/ai-assistant/chat.
      signal: AbortSignal.timeout(35_000),
    });

    if (429 === res.status) {
      return NextResponse.json({ ok: false, error: "rate_limited" }, { status: 429 });
    }
    if (503 === res.status) {
      return NextResponse.json({ ok: false, error: "unavailable" }, { status: 503 });
    }
    if (!res.ok) {
      console.error(`[ai-assistant] POST /assistant/summarize -> ${res.status} ${res.statusText}`);
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
    console.error("[ai-assistant] POST /assistant/summarize failed", error);
    return NextResponse.json({ ok: false, error: "network_error" }, { status: 502 });
  }
}
