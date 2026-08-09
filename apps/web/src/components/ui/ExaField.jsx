import { cx } from "./cx";

export function ExaField({ label, error, helper, icon: Icon, suffix, children, className = "", inputClassName = "", ...props }) {
  return (
    <label className={cx("block space-y-2", className)}>
      {label ? <span className="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--exa-gold-light)]/80">{label}</span> : null}
      <span className={cx("exa-input flex min-h-12 items-center rounded-2xl px-4 transition", inputClassName)}>
        {Icon ? <Icon className="mr-3 h-4 w-4 shrink-0 text-[var(--exa-text-muted)]" aria-hidden="true" /> : null}
        {children || <input className="w-full bg-transparent text-sm text-[var(--exa-text-primary)] outline-none placeholder:text-[var(--exa-text-disabled)]" {...props} />}
        {suffix ? <span className="ml-3 shrink-0 text-xs font-medium text-[var(--exa-text-muted)]">{suffix}</span> : null}
      </span>
      {error ? <span className="block text-xs text-rose-300">{error}</span> : helper ? <span className="block text-xs text-[var(--exa-text-muted)]">{helper}</span> : null}
    </label>
  );
}