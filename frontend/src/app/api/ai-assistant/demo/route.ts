import { NextRequest, NextResponse } from "next/server";
import { API_URL } from "@/lib/auth/config";
import { visitorHeadersFrom } from "@/lib/http/visitor";
import { rateLimit } from "@/lib/rate-limit";

/**
 * Proxy public de la démo WhatsApp de l'accueil vers POST /api/assistant/demo
 * (App\ApiResource\AiDemoChatApiResource). Même structure que le proxy du
 * chat, avec sa PROPRE clé de limitation : jouer avec la démo n'entame pas
 * le quota de questions posées à l'assistant du site.
 *
 * Réponse volontairement pauvre : `{ ok: false }` pour tout échec — le
 * composant rejoue alors la réponse scénarisée, sans message d'erreur.
 */
const MAX_MESSAGE = 300;
const MAX_TURN = 400;
const MAX_HISTORY = 6;
/** Borne de App\State\AiDemoChatProcessor::MAX_SUGGESTION : au-delà, ce n'est plus une réponse rapide. */
const MAX_SUGGESTION = 60;

type Turn = { role: "user" | "assistant"; text: string };

function cleanHistory(raw: unknown): Turn[] | null {
  if (!Array.isArray(raw) || raw.length > MAX_HISTORY) return null;
  const turns: Turn[] = [];
  for (const t of raw) {
    if (!t || typeof t !== "object") return null;
    const { role, text } = t as Record<string, unknown>;
    if ((role !== "user" && role !== "assistant") || typeof text !== "string" || !text.trim() || text.length > MAX_TURN) return null;
    turns.push({ role, text });
  }
  return turns;
}

export async function POST(request: NextRequest) {
  if (!(await rateLimit("ai-demo-chat", 15, 60 * 60 * 1000))) {
    return NextResponse.json({ ok: false }, { status: 429 });
  }

  const body = (await request.json().catch(() => null)) as Record<string, unknown> | null;
  const message = typeof body?.message === "string" ? body.message.trim() : "";
  const history = cleanHistory(body?.history ?? []);
  if (!message || message.length > MAX_MESSAGE || history === null) {
    return NextResponse.json({ ok: false }, { status: 400 });
  }

  try {
    const res = await fetch(`${API_URL}/assistant/demo`, {
      method: "POST",
      headers: {
        "Content-Type": "application/ld+json",
        Accept: "application/ld+json",
        ...visitorHeadersFrom(request.headers),
      },
      body: JSON.stringify({ message, history, locale: body?.locale === "en" ? "en" : "fr" }),
      cache: "no-store",
      signal: AbortSignal.timeout(35_000),
    });
    if (!res.ok) {
      if (res.status !== 429) console.error(`[ai-demo] POST /assistant/demo -> ${res.status}`);
      return NextResponse.json({ ok: false }, { status: res.status === 429 ? 429 : 502 });
    }

    const data = (await res.json()) as { answer?: unknown; event?: unknown; degraded?: unknown; suggestions?: unknown };
    if (data.degraded === true || typeof data.answer !== "string" || !data.answer.trim()) {
      return NextResponse.json({ ok: false });
    }
    const event = data.event === "order" || data.event === "handover" ? data.event : null;
    const suggestions = Array.isArray(data.suggestions)
      ? data.suggestions
          .filter((s): s is string => typeof s === "string" && s.trim() !== "" && s.length <= MAX_SUGGESTION)
          .slice(0, 3)
      : [];
    return NextResponse.json({ ok: true, answer: data.answer, event, suggestions });
  } catch (error) {
    console.error("[ai-demo] POST /assistant/demo failed", error);
    return NextResponse.json({ ok: false }, { status: 502 });
  }
}
