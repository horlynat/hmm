import { Skeleton } from "@/components/ui";

export default function Loading() {
  return (
    <div className="max-w-190 space-y-6">
      <div className="flex items-center gap-3.5">
        <Skeleton className="h-11 w-11 shrink-0 rounded-xl" />
        <div className="flex-1 space-y-2">
          <Skeleton className="h-7 w-44" />
          <Skeleton className="h-4 w-64" />
        </div>
      </div>

      <Skeleton className="h-[72px] w-56 rounded-[var(--radius-lg)]" />

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        {Array.from({ length: 4 }).map((_, i) => (
          <Skeleton key={i} className="h-44 rounded-[var(--radius-lg)]" />
        ))}
      </div>
    </div>
  );
}
