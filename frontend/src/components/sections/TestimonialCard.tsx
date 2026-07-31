import { Card } from "@/components/ui";
import type { Testimonial } from "@/lib/types";

export function TestimonialCard({
  testimonial,
  className,
}: {
  testimonial: Testimonial;
  className?: string;
}) {
  const initial = testimonial.author.charAt(0).toUpperCase();
  const rating = testimonial.rating ? Math.round(Number(testimonial.rating)) : 0;

  return (
    <Card className={className}>
      <p className="mb-4 text-sm opacity-75">« {testimonial.content} »</p>
      <div className="flex items-center gap-2.5">
        <div
          className="flex h-9 w-9 items-center justify-center rounded-full bg-brand-light text-sm font-bold text-[var(--color-on-brand-light)]"
          style={{ fontFamily: "var(--font-heading)" }}
        >
          {initial}
        </div>
        <div>
          <div
            className="text-sm font-bold"
            style={{ fontFamily: "var(--font-heading)" }}
          >
            {testimonial.author}
          </div>
          {rating > 0 && (
            <div className="text-xs opacity-60" aria-hidden="true">
              {"★".repeat(rating)}
            </div>
          )}
        </div>
      </div>
    </Card>
  );
}
