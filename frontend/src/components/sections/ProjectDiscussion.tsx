"use client";

import { useEffect, useState, useTransition, type FormEvent } from "react";
import { MessageSquare, Send, X } from "lucide-react";
import clsx from "clsx";
import { postProjectComment } from "@/lib/auth/actions";
import type { SessionComment } from "@/lib/types";

interface ProjectDiscussionProps {
  projectId: number;
  initialComments: SessionComment[];
  locale: string;
  labels: {
    title: string;
    empty: string;
    placeholder: string;
    send: string;
    sending: string;
    you: string;
    close: string;
    open: string;
    readOnly?: string;
  };
  /** Désactive l'envoi (formulaire masqué) — la lecture des messages reste possible. Utilisé pour un profil freelance incomplet. */
  readOnly?: boolean;
}

/**
 * Fil de discussion d'un projet, présenté en fenêtre contextuelle flottante
 * (même schéma que AiAssistantWidget) plutôt qu'en section de bas de page :
 * un projet avec beaucoup de contenu (galerie, stack, résultats…) rendait la
 * discussion inaccessible sans défiler toute la fiche.
 */
export function ProjectDiscussion({ projectId, initialComments, locale, labels, readOnly = false }: ProjectDiscussionProps) {
  const [open, setOpen] = useState(false);
  const [comments, setComments] = useState(initialComments);
  const [content, setContent] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [isPending, startTransition] = useTransition();

  useEffect(() => {
    function onKeyDown(event: KeyboardEvent) {
      if (event.key === "Escape") setOpen(false);
    }
    document.addEventListener("keydown", onKeyDown);
    return () => document.removeEventListener("keydown", onKeyDown);
  }, []);

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const trimmed = content.trim();
    if (!trimmed) return;

    setError(null);
    startTransition(async () => {
      const result = await postProjectComment(projectId, trimmed);
      if (result.ok && result.comment) {
        setComments((prev) => [...prev, result.comment!]);
        setContent("");
      } else {
        setError(result.ok ? null : result.error);
      }
    });
  }

  return (
    <>
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        aria-label={open ? labels.close : labels.open}
        className="fixed bottom-6 right-6 z-40 flex items-center gap-2.5 rounded-full px-5 py-3.5 text-sm font-bold text-white shadow-lg"
        style={{
          fontFamily: "var(--font-heading)",
          background: "linear-gradient(135deg, var(--cta-gradient-from), var(--cta-gradient-to) 70%)",
        }}
      >
        <MessageSquare size={17} aria-hidden="true" />
        <span className="hidden sm:inline">{labels.title}</span>
        {comments.length > 0 && (
          <span className="flex h-5 min-w-5 items-center justify-center rounded-full bg-white/25 px-1 text-xs">
            {comments.length}
          </span>
        )}
      </button>

      <div
        role="dialog"
        aria-label={labels.title}
        className={clsx(
          "fixed bottom-24 right-6 z-50 flex h-[480px] max-h-[calc(100vh-8rem)] w-[380px] max-w-[calc(100vw-2rem)] flex-col overflow-hidden rounded-[var(--radius-lg)] border border-[var(--border-soft)] bg-bg-card shadow-2xl",
          open ? "flex" : "hidden",
        )}
      >
        <div
          className="flex items-center justify-between gap-2 px-4 py-3.5 text-white"
          style={{ background: "linear-gradient(135deg, var(--cta-gradient-from), var(--cta-gradient-to) 70%)" }}
        >
          <div className="flex items-center gap-2 text-sm font-bold" style={{ fontFamily: "var(--font-heading)" }}>
            <MessageSquare size={16} aria-hidden="true" />
            {labels.title}
          </div>
          <button type="button" onClick={() => setOpen(false)} aria-label={labels.close} className="text-lg leading-none">
            <X size={18} aria-hidden="true" />
          </button>
        </div>

        {comments.length === 0 ? (
          <p className="flex-1 p-5 text-center text-sm opacity-60">{labels.empty}</p>
        ) : (
          <ul className="flex-1 space-y-4 overflow-y-auto p-4">
            {comments.map((comment) => (
              <li key={comment.id} className={comment.isMine ? "ml-auto max-w-[85%] text-right" : "mr-auto max-w-[85%]"}>
                <div
                  className={
                    comment.isMine
                      ? "inline-block rounded-[var(--radius-md)] rounded-br-[4px] bg-brand-primary px-3.5 py-2.5 text-left text-[var(--color-on-brand-primary)]"
                      : "inline-block rounded-[var(--radius-md)] rounded-bl-[4px] bg-brand-light px-3.5 py-2.5 text-left text-[var(--color-on-brand-light)]"
                  }
                >
                  <p className="mb-1 text-xs font-semibold opacity-70">
                    {comment.isMine ? labels.you : (comment.author.fullName ?? comment.author.email)}
                  </p>
                  <p className="whitespace-pre-line text-sm">{comment.content}</p>
                </div>
                <p className="mt-1 text-[11px] opacity-45">
                  {new Date(comment.createdAt).toLocaleString(locale, {
                    day: "numeric",
                    month: "short",
                    hour: "2-digit",
                    minute: "2-digit",
                  })}
                </p>
              </li>
            ))}
          </ul>
        )}

        {readOnly ? (
          <p className="border-t border-[var(--border-softer)] p-3 text-center text-xs opacity-60">{labels.readOnly}</p>
        ) : (
          <form onSubmit={handleSubmit} className="flex items-end gap-2 border-t border-[var(--border-softer)] p-3">
            <textarea
              value={content}
              onChange={(e) => setContent(e.target.value)}
              placeholder={labels.placeholder}
              rows={1}
              maxLength={5000}
              disabled={isPending}
              className="input min-h-10 flex-1 resize-none"
            />
            <button
              type="submit"
              disabled={isPending || !content.trim()}
              aria-label={isPending ? labels.sending : labels.send}
              className="btn-primary shrink-0 !px-3"
            >
              <Send size={16} aria-hidden="true" />
            </button>
          </form>
        )}
        {error && <p className="px-3 pb-3 text-xs text-danger">{error}</p>}
      </div>
    </>
  );
}
