function AllocationBar({ label, percentage, colorClass }) {
  return (
    <div className="rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4">
      <div className="mb-3 flex items-center justify-between gap-2">
        <p className="text-sm font-medium text-[var(--exa-text-secondary)] sm:text-base">{label}</p>
        <p className="text-base font-semibold text-[var(--exa-gold)] sm:text-lg">{percentage}%</p>
      </div>
      <div className="h-2.5 overflow-hidden rounded-full bg-[var(--exa-surface-hover)]">
        <div
          className={`allocation-fill h-full rounded-full ${colorClass}`}
          style={{ width: `${percentage}%` }}
          role="progressbar"
          aria-valuenow={percentage}
          aria-valuemin={0}
          aria-valuemax={100}
          aria-label={`${label} allocation`}
        />
      </div>
    </div>
  );
}

export default AllocationBar;
