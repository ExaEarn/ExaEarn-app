import { cx } from "./cx";

const variants = {
  primary: "exa-button-primary font-semibold",
  secondary: "exa-button-secondary font-semibold",
  ghost: "border border-transparent bg-transparent text-slate-300 hover:bg-white/[0.04] hover:text-white",
  danger: "border border-rose-400/25 bg-rose-500/12 text-rose-100 hover:bg-rose-500/18",
};

const sizes = {
  sm: "min-h-9 rounded-xl px-3 py-2 text-xs",
  md: "min-h-11 rounded-2xl px-4 py-3 text-sm",
  lg: "min-h-12 rounded-2xl px-5 py-3.5 text-base",
  icon: "inline-flex h-10 w-10 items-center justify-center rounded-xl p-0",
};

export function ExaButton({ as: Component = "button", variant = "primary", size = "md", loading = false, disabled = false, className = "", children, ...props }) {
  return (
    <Component
      className={cx(
        "inline-flex items-center justify-center gap-2 text-center transition duration-200 exa-focusable disabled:cursor-not-allowed disabled:opacity-55",
        variants[variant] || variants.primary,
        sizes[size] || sizes.md,
        className
      )}
      disabled={Component === "button" ? disabled || loading : undefined}
      aria-busy={loading || undefined}
      {...props}
    >
      {loading ? <span className="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" aria-hidden="true" /> : null}
      {children}
    </Component>
  );
}