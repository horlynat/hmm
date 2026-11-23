import type { ReactNode } from "react";
import type { ProtoIconName } from "@/components/proto/assets";
import { ProtoIcon } from "@/components/proto/ProtoSvg";

/**
 * Case à cocher visuelle du questionnaire guidé (prototype `.tile`) —
 * équivalent, en classes du prototype, de `QuoteWizardOptionCard`.
 */
export function OptionTile({
  selected,
  onClick,
  icon,
  description,
  children,
}: {
  selected: boolean;
  onClick: () => void;
  icon?: ProtoIconName;
  description?: string;
  children: ReactNode;
}) {
  return (
    <button type="button" role="radio" aria-checked={selected} className={selected ? "tile on" : "tile"} onClick={onClick}>
      {icon && (
        <span className="oic">
          <ProtoIcon name={icon} />
        </span>
      )}
      <span>
        <b>{children}</b>
        {description && <small>{description}</small>}
      </span>
    </button>
  );
}
