"use client";

import { useRef, type MouseEvent, type ReactNode } from "react";
import { ExternalLink } from "lucide-react";
import { useReducedMotion } from "@/lib/useReducedMotion";

interface ProjectDeviceFrameProps {
  children: ReactNode;
  /** URL du site en production, si connue (Project.link) — affichée dans la barre d'adresse factice et proposée comme lien "voir en direct" au survol. Sans effet si absente. */
  liveUrl?: string;
  liveLabel: string;
}

/**
 * Cadre "navigateur" (barre de titre à trois points + barre d'adresse) autour
 * de l'image de couverture d'un projet, avec un léger effet de bascule 3D au
 * survol de la souris — pensé pour donner l'impression d'une vraie fenêtre,
 * pas juste une capture d'écran encadrée.
 *
 * Pas d'iframe live vers `project.link` malgré la tentation : vérifié en
 * pratique qu'aucun des liens actuellement enregistrés (y compris le domaine
 * qui a l'air réel) ne répond — les deux autres sont des domaines
 * *.example.com de démonstration. Un iframe vers une URL qui ne répond pas
 * (ou bloquée par X-Frame-Options — indétectable en JS, pas d'événement
 * d'erreur fiable) afficherait une erreur de navigateur par défaut, pire que
 * l'image statique existante. Le lien "voir en direct" reste un vrai lien
 * sortant, qui échoue proprement (nouvel onglet) plutôt que dans le cadre.
 */
export function ProjectDeviceFrame({ children, liveUrl, liveLabel }: ProjectDeviceFrameProps) {
  const ref = useRef<HTMLDivElement>(null);
  const reduce = useReducedMotion();

  function handleMouseMove(event: MouseEvent<HTMLDivElement>) {
    if (reduce) return;
    const el = ref.current;
    if (!el) return;
    const rect = el.getBoundingClientRect();
    const px = (event.clientX - rect.left) / rect.width;
    const py = (event.clientY - rect.top) / rect.height;
    const rotateY = (px - 0.5) * 10;
    const rotateX = (0.5 - py) * 8;
    el.style.transition = "none";
    el.style.transform = `perspective(1200px) rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
  }

  function handleMouseLeave() {
    const el = ref.current;
    if (!el) return;
    el.style.transition = "transform 0.5s cubic-bezier(0.22, 1, 0.36, 1)";
    el.style.transform = "";
  }

  let hostname: string | null = null;
  if (liveUrl) {
    try {
      hostname = new URL(liveUrl).hostname;
    } catch {
      hostname = null;
    }
  }

  return (
    <div
      ref={ref}
      onMouseMove={handleMouseMove}
      onMouseLeave={handleMouseLeave}
      className="group overflow-hidden rounded-[var(--radius-lg)] border border-[var(--border-softer)] bg-bg-card shadow-lg will-change-transform"
    >
      <div className="flex items-center gap-3 border-b border-[var(--border-softer)] bg-bg-default px-4 py-2.5" aria-hidden="true">
        <div className="flex shrink-0 gap-1.5">
          <span className="h-2.5 w-2.5 rounded-full" style={{ background: "#ff5f57" }} />
          <span className="h-2.5 w-2.5 rounded-full" style={{ background: "#febc2e" }} />
          <span className="h-2.5 w-2.5 rounded-full" style={{ background: "#28c840" }} />
        </div>
        {hostname && (
          <div className="flex-1 truncate rounded-full bg-brand-light/40 px-3 py-1 text-center font-mono text-[11px] opacity-60">
            {hostname}
          </div>
        )}
      </div>
      <div className="relative">
        {children}
        {liveUrl && (
          <a
            href={liveUrl}
            target="_blank"
            rel="noopener"
            className="absolute inset-0 flex items-end justify-end p-4 opacity-0 transition-opacity group-hover:opacity-100"
            style={{ background: "linear-gradient(to top, rgba(0,0,0,0.35), transparent 45%)" }}
          >
            <span className="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-2 text-xs font-semibold text-brand-dark shadow-sm">
              {liveLabel}
              <ExternalLink size={13} aria-hidden="true" />
            </span>
          </a>
        )}
      </div>
    </div>
  );
}
