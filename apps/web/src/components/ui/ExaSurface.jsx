import { cx } from "./cx";

const variants = {
  default: "exa-surface",
  elevated: "exa-surface-elevated",
  subtle: "border border-white/8 bg-white/[0.025]",
};

export function ExaSurface({ as: Component = "section", variant = "default", className = "", children, ...props }) {
  return (
    <Component className={cx("rounded-2xl", variants[variant] || variants.default, className)} {...props}>
      {children}
    </Component>
  );
}