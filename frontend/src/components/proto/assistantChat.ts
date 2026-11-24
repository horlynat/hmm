/**
 * Logique pure de l'assistant du site (SiteAssistant), testée à part.
 *
 * 1. Historique envoyé au backend — bornes de AiAssistantChatApiResource :
 *    8 tours au plus, 1000 caractères par tour, rôles alternés, dernier tour
 *    « assistant ». L'ancien widget envoyait TOUT l'historique : dès la 5e
 *    question (ou après une réponse longue) le backend répondait 422 et
 *    chaque question suivante affichait « indisponible ». On n'envoie donc
 *    que les dernières paires (question, vraie réponse), tronquées, et jamais
 *    un tour de repli ou d'erreur (sinon une question suspecte bloquait
 *    toutes les suivantes via le filtre de l'historique).
 *
 * 2. Rendu d'une réponse : sous-ensemble Markdown (paragraphes, listes « - »,
 *    **gras**, liens) transformé en STRUCTURE, rendue ensuite comme texte
 *    React — jamais d'HTML injecté. Liens retenus : chemins internes connus
 *    (/offres/slug…) ou https sur les domaines déjà admis côté backend
 *    (AiAssistantOutputSanitizer) ; tout autre lien devient du texte simple.
 */

export type ChatMsg =
  | { who: "user"; text: string; inHistory: boolean }
  | { who: "bot"; text: string; inHistory: boolean; whatsapp: boolean };

export type HistoryTurn = { role: "user" | "assistant"; text: string };

export const MAX_HISTORY_TURNS = 8;
export const MAX_TURN_LENGTH = 1000;

function clip(text: string): string {
  return text.length <= MAX_TURN_LENGTH ? text : `${text.slice(0, MAX_TURN_LENGTH - 1)}…`;
}

export function buildHistory(msgs: ChatMsg[]): HistoryTurn[] {
  const pairs: [string, string][] = [];
  for (let i = 0; i < msgs.length - 1; i++) {
    const q = msgs[i];
    const a = msgs[i + 1];
    if (q.who === "user" && a.who === "bot" && q.inHistory && a.inHistory && q.text.trim() && a.text.trim()) {
      pairs.push([q.text, a.text]);
      i++;
    }
  }
  return pairs
    .slice(-Math.floor(MAX_HISTORY_TURNS / 2))
    .flatMap(([q, a]) => [
      { role: "user" as const, text: clip(q) },
      { role: "assistant" as const, text: clip(a) },
    ]);
}

export type Inline =
  | { kind: "text"; text: string }
  | { kind: "bold"; text: string }
  | { kind: "link"; text: string; href: string; internal: boolean };

export type Block = { kind: "p"; parts: Inline[] } | { kind: "ul"; items: Inline[][] };

const INTERNAL = /^\/(?:(?:offres|realisations|blog)\/[a-z0-9]+(?:-[a-z0-9]+)*|offres|blog|contact|a-propos)$/;
const EXTERNAL_HOSTS = ["horlynat.com", "github.com", "linkedin.com"];

/** Lien sûr ? Renvoie l'adresse retenue, ou null (rendu alors en texte simple). */
export function safeHref(raw: string): { href: string; internal: boolean } | null {
  const href = raw.trim();
  if (INTERNAL.test(href)) return { href, internal: true };
  try {
    const u = new URL(href);
    const host = u.hostname.toLowerCase();
    if (u.protocol === "https:" && !u.username && !u.password && EXTERNAL_HOSTS.some((h) => host === h || host.endsWith(`.${h}`))) {
      return { href: u.toString(), internal: false };
    }
  } catch {
    /* adresse invalide */
  }
  return null;
}

const INLINE = /\*\*([^*\n]+)\*\*|\[([^\]\n]+)\]\(([^)\s]+)\)/g;

export function parseInline(line: string): Inline[] {
  const out: Inline[] = [];
  let last = 0;
  for (const m of line.matchAll(INLINE)) {
    const at = m.index ?? 0;
    if (at > last) out.push({ kind: "text", text: line.slice(last, at) });
    if (m[1] !== undefined) {
      out.push({ kind: "bold", text: m[1] });
    } else {
      const link = safeHref(m[3]);
      out.push(link ? { kind: "link", text: m[2], ...link } : { kind: "text", text: m[2] });
    }
    last = at + m[0].length;
  }
  if (last < line.length) out.push({ kind: "text", text: line.slice(last) });
  // Astérisques orphelins (gras mal fermé) : retirés plutôt qu'affichés.
  return out.map((p) => (p.kind === "text" ? { ...p, text: p.text.replace(/\*\*/g, "") } : p)).filter((p) => p.text !== "");
}

export function parseAnswer(text: string): Block[] {
  const blocks: Block[] = [];
  let para: string[] = [];
  let list: Inline[][] = [];
  const flushPara = () => {
    if (para.length) blocks.push({ kind: "p", parts: parseInline(para.join(" ")) });
    para = [];
  };
  const flushList = () => {
    if (list.length) blocks.push({ kind: "ul", items: list });
    list = [];
  };
  for (const raw of text.replace(/\r\n?/g, "\n").split("\n")) {
    const line = raw.trim();
    const item = /^(?:[-•*]|\d+[.)])\s+(.+)$/.exec(line);
    if (!line) {
      flushPara();
      flushList();
    } else if (item) {
      flushPara();
      list.push(parseInline(item[1]));
    } else {
      flushList();
      para.push(line.replace(/^#{1,6}\s+/, ""));
    }
  }
  flushPara();
  flushList();
  return blocks.filter((b) => (b.kind === "p" ? b.parts.length > 0 : b.items.length > 0));
}

/** Liens internes distincts d'une réponse (cartes « voir l'offre » sous la bulle). */
export function internalLinks(blocks: Block[], max = 2): { text: string; href: string }[] {
  const seen = new Map<string, string>();
  const visit = (parts: Inline[]) =>
    parts.forEach((p) => {
      if (p.kind === "link" && p.internal && !seen.has(p.href)) seen.set(p.href, p.text);
    });
  blocks.forEach((b) => (b.kind === "p" ? visit(b.parts) : b.items.forEach(visit)));
  return [...seen].slice(0, max).map(([href, text]) => ({ href, text }));
}

/** Nombre de caractères visibles d'une réponse structurée. */
export function visibleLength(blocks: Block[]): number {
  const n = (parts: Inline[]) => parts.reduce((t, p) => t + p.text.length, 0);
  return blocks.reduce((t, b) => t + (b.kind === "p" ? n(b.parts) : b.items.reduce((u, it) => u + n(it), 0)), 0);
}

/**
 * Les `budget` premiers caractères visibles, structure conservée : l'effet
 * « réponse en cours d'écriture » ne montre jamais de Markdown à moitié
 * tapé (« [Titre](/off »), seulement du texte déjà mis en forme.
 */
export function truncateBlocks(blocks: Block[], budget: number): Block[] {
  let left = budget;
  const cut = (parts: Inline[]): Inline[] => {
    const out: Inline[] = [];
    for (const p of parts) {
      if (left <= 0) break;
      out.push(p.text.length <= left ? p : { ...p, text: p.text.slice(0, left) });
      left -= p.text.length;
    }
    return out;
  };
  const out: Block[] = [];
  for (const b of blocks) {
    if (left <= 0) break;
    if (b.kind === "p") {
      out.push({ kind: "p", parts: cut(b.parts) });
    } else {
      const items: Inline[][] = [];
      for (const it of b.items) {
        if (left <= 0) break;
        items.push(cut(it));
      }
      out.push({ kind: "ul", items });
    }
  }
  return out;
}
