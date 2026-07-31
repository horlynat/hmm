/**
 * Icônes utilisées à la fois par le teaser "À propos" de la home et par la
 * page /a-propos complète (mêmes clés de traduction `about.why.card1-4`) —
 * partagées pour rester visuellement identiques d'une page à l'autre.
 */

export function DevicesIcon() {
  return (
    <svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20">
      <rect x="2.5" y="5" width="13.5" height="9.5" rx="1.2" fill="none" stroke="currentColor" strokeWidth="1.5" />
      <path d="M6.5 17.5h5.5" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
      <rect x="17.2" y="7" width="4.3" height="10.5" rx="1" fill="none" stroke="currentColor" strokeWidth="1.5" />
    </svg>
  );
}

export function DualLensIcon() {
  return (
    <svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20">
      <circle cx="9" cy="12" r="6" fill="none" stroke="currentColor" strokeWidth="1.5" />
      <circle cx="15" cy="12" r="6" fill="none" stroke="currentColor" strokeWidth="1.5" />
    </svg>
  );
}

export function ShieldIcon() {
  return (
    <svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20">
      <path
        d="M12 3.5 5 6v5.5c0 4.5 3 7.5 7 9 4-1.5 7-4.5 7-9V6l-7-2.5Z"
        fill="none"
        stroke="currentColor"
        strokeWidth="1.5"
        strokeLinejoin="round"
      />
      <path
        d="M9 12.2l2 2 4-4.2"
        fill="none"
        stroke="currentColor"
        strokeWidth="1.5"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}

export function SparkleChatIcon() {
  return (
    <svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20">
      <path
        d="M4 5.5h13a2 2 0 0 1 2 2V13a2 2 0 0 1-2 2H10l-4 3.5V15H4a2 2 0 0 1-2-2V7.5a2 2 0 0 1 2-2Z"
        fill="none"
        stroke="currentColor"
        strokeWidth="1.5"
        strokeLinejoin="round"
      />
      <path d="M17.5 3.5l.6 1.4 1.4.6-1.4.6-.6 1.4-.6-1.4-1.4-.6 1.4-.6.6-1.4Z" fill="currentColor" />
    </svg>
  );
}

export function PaletteIcon() {
  return (
    <svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20">
      <path
        d="M12 3.5a8.5 8.5 0 1 0 0 17c1 0 1.6-.7 1.6-1.5 0-.4-.15-.7-.4-1-.25-.3-.4-.6-.4-1 0-.8.65-1.5 1.5-1.5H16a3.5 3.5 0 0 0 3.5-3.5c0-4.4-3.4-8.5-7.5-8.5Z"
        fill="none"
        stroke="currentColor"
        strokeWidth="1.5"
        strokeLinejoin="round"
      />
      <circle cx="8" cy="10.5" r="0.9" fill="currentColor" />
      <circle cx="12" cy="8" r="0.9" fill="currentColor" />
      <circle cx="8.5" cy="14.5" r="0.9" fill="currentColor" />
    </svg>
  );
}
