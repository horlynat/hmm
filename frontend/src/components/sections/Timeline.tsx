interface TimelineItem {
  title: string;
  desc: string;
  /** Plage de dates déjà formatée (ex: "01/2021 – Aujourd'hui") — optionnelle, affichée sous le titre. */
  date?: string;
}

export function Timeline({ items }: { items: TimelineItem[] }) {
  return (
    <ul className="m-0 list-none p-0">
      {items.map((item, i) => (
        <li
          key={i}
          className="relative border-l-2 border-[var(--border-soft)] py-0 pb-6 pl-7 last:border-transparent last:pb-0"
        >
          <span className="absolute -left-[7px] top-0.5 h-3 w-3 rounded-full border-2 border-brand-accent bg-bg-default" />
          <div className="mb-1 flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
            <span className="text-base font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
              {item.title}
            </span>
            {item.date && <span className="font-mono text-xs opacity-60">{item.date}</span>}
          </div>
          <p className="text-sm opacity-70">{item.desc}</p>
        </li>
      ))}
    </ul>
  );
}
