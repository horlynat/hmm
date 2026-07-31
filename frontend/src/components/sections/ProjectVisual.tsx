/**
 * Motif SVG abstrait remplaçant un bloc de couleur uni sur `ProjectCard` — le
 * type `Project` n'a pas de champ image côté backend. Purement décoratif
 * (`aria-hidden`), le titre du projet est déjà affiché en texte juste après.
 * Variante choisie par `project.id % 3` pour éviter que 3 cartes voisines
 * affichent exactement le même motif.
 */
function BrowserVariant() {
  return (
    <svg viewBox="0 0 200 130" width="100%" height="100%" aria-hidden="true">
      <rect x="20" y="18" width="160" height="94" rx="8" fill="var(--color-bg-default)" stroke="var(--border-soft)" strokeWidth="1.5" />
      <rect x="20" y="18" width="160" height="20" rx="8" fill="var(--color-brand-primary)" opacity="0.12" />
      <circle cx="32" cy="28" r="2.5" fill="var(--color-brand-primary)" opacity="0.5" />
      <circle cx="41" cy="28" r="2.5" fill="var(--color-brand-primary)" opacity="0.35" />
      <circle cx="50" cy="28" r="2.5" fill="var(--color-brand-primary)" opacity="0.2" />
      <rect x="32" y="50" width="80" height="7" rx="3.5" fill="var(--color-brand-accent)" opacity="0.5" />
      <rect x="32" y="64" width="120" height="6" rx="3" fill="var(--border-soft)" />
      <rect x="32" y="76" width="96" height="6" rx="3" fill="var(--border-soft)" />
      <rect x="32" y="92" width="56" height="10" rx="5" fill="var(--color-brand-primary)" opacity="0.25" />
    </svg>
  );
}

function NetworkVariant() {
  return (
    <svg viewBox="0 0 200 130" width="100%" height="100%" aria-hidden="true">
      <path d="M45 95 L100 40 L155 95 M100 40 L100 95" stroke="var(--border-soft)" strokeWidth="1.5" fill="none" />
      <circle cx="100" cy="40" r="9" fill="var(--color-bg-default)" stroke="var(--color-brand-accent)" strokeWidth="2" />
      <circle cx="45" cy="95" r="8" fill="var(--color-bg-default)" stroke="var(--color-brand-primary)" strokeWidth="1.5" />
      <circle cx="155" cy="95" r="8" fill="var(--color-bg-default)" stroke="var(--color-brand-primary)" strokeWidth="1.5" />
      <circle cx="100" cy="95" r="6" fill="var(--color-bg-default)" stroke="var(--border-soft)" strokeWidth="1.5" />
    </svg>
  );
}

function ProgressVariant() {
  const bars = [38, 62, 50, 78, 60];
  return (
    <svg viewBox="0 0 200 130" width="100%" height="100%" aria-hidden="true">
      {bars.map((h, i) => (
        <rect
          key={i}
          x={30 + i * 30}
          y={105 - h}
          width="18"
          height={h}
          rx="4"
          fill={i === bars.length - 1 ? "var(--color-brand-accent)" : "var(--color-brand-primary)"}
          opacity={i === bars.length - 1 ? 0.9 : 0.2 + i * 0.1}
        />
      ))}
      <path d="M28 60 L58 45 L88 68 L118 30 L148 50" stroke="var(--color-brand-accent)" strokeWidth="1.5" fill="none" opacity="0.6" />
    </svg>
  );
}

const variants = [BrowserVariant, NetworkVariant, ProgressVariant];

export function ProjectVisual({ seed }: { seed: number }) {
  const Variant = variants[((seed % variants.length) + variants.length) % variants.length];
  return <Variant />;
}
