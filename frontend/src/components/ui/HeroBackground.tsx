/**
 * Décor de fond des sections hero (double halo dégradé flouté + grille de
 * points masquée en ellipse) — partagé entre les pages pour garder le même
 * habillage visuel d'une page à l'autre.
 */
export function HeroBackground() {
  return (
    <>
      <div
        aria-hidden="true"
        className="pointer-events-none absolute inset-x-0 -top-40 -z-10 transform-gpu overflow-hidden blur-3xl sm:-top-80"
      >
        <div
          style={{
            clipPath:
              "polygon(74.1% 44.1%, 100% 61.6%, 97.5% 26.9%, 85.5% 0.1%, 80.7% 2%, 72.5% 32.5%, 60.2% 62.4%, 52.4% 68.1%, 47.5% 58.3%, 45.2% 34.5%, 27.5% 76.7%, 0.1% 64.9%, 17.9% 100%, 27.6% 76.8%, 76.1% 97.7%, 74.1% 44.1%)",
          }}
          className="relative left-1/2 aspect-[1155/678] w-[36rem] -translate-x-1/2 rotate-[30deg] bg-linear-to-tr from-[var(--color-brand-accent)] to-[var(--color-brand-primary)] opacity-25 sm:left-[calc(50%-22rem)] sm:w-[72rem]"
        />
      </div>
      <div
        aria-hidden="true"
        className="pointer-events-none absolute inset-x-0 top-[calc(100%-13rem)] -z-10 transform-gpu overflow-hidden blur-3xl sm:top-[calc(100%-24rem)]"
      >
        <div
          style={{
            clipPath:
              "polygon(74.1% 44.1%, 100% 61.6%, 97.5% 26.9%, 85.5% 0.1%, 80.7% 2%, 72.5% 32.5%, 60.2% 62.4%, 52.4% 68.1%, 47.5% 58.3%, 45.2% 34.5%, 27.5% 76.7%, 0.1% 64.9%, 17.9% 100%, 27.6% 76.8%, 76.1% 97.7%, 74.1% 44.1%)",
          }}
          className="relative left-1/2 aspect-[1155/678] w-[36rem] -translate-x-1/2 bg-linear-to-tr from-[var(--color-brand-accent)] to-[var(--color-brand-primary)] opacity-25 sm:left-[calc(50%+9rem)] sm:w-[72rem]"
        />
      </div>
      <div
        aria-hidden="true"
        className="pointer-events-none absolute inset-0"
        style={{
          backgroundImage:
            "radial-gradient(circle at 1px 1px, var(--border-soft) 1px, transparent 0)",
          backgroundSize: "32px 32px",
          WebkitMaskImage:
            "radial-gradient(ellipse 60% 55% at 50% 0%, black 40%, transparent 100%)",
          maskImage:
            "radial-gradient(ellipse 60% 55% at 50% 0%, black 40%, transparent 100%)",
        }}
      />
    </>
  );
}
