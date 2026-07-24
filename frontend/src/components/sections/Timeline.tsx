interface TimelineItem {
  title: string;
  desc: string;
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
          <div
            className="mb-1 text-base font-semibold"
            style={{ fontFamily: "var(--font-heading)" }}
          >
            {item.title}
          </div>
          <div className="text-sm opacity-70">{item.desc}</div>
        </li>
      ))}
    </ul>
  );
}
